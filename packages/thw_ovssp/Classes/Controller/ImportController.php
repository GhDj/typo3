<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Controller;

use Init\Thw\Ovssp\Service\ImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ImportController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ImportService $importService,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assign('activeTab', 'import');
        return $moduleTemplate->renderResponse('Index');
    }

    public function uploadAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $type = (string)($body['type'] ?? '');
        $realm = strtoupper((string)($body['realm'] ?? ''));
        $pid = (int)($body['pid'] ?? 0);

        $errors = [];

        if (!in_array($type, ['users', 'orgunits', 'directories'], true)) {
            $errors[] = 'Please select a valid import type.';
        }

        if ($type === 'users' && !in_array($realm, ['HA', 'EA'], true)) {
            $errors[] = 'AD Realm (HA or EA) is required for user imports.';
        }

        $uploadedFiles = $request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['importFile'] ?? null;

        if ($uploadedFile === null || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload a valid CSV file.';
        }

        if (!empty($errors)) {
            $moduleTemplate = $this->moduleTemplateFactory->create($request);
            $moduleTemplate->assignMultiple([
                'activeTab' => 'import',
                'errors' => $errors,
                'selectedType' => $type,
                'selectedRealm' => $realm,
                'pid' => $pid,
            ]);
            return $moduleTemplate->renderResponse('Index');
        }

        $tempFile = GeneralUtility::tempnam('ovssp_import_', '.csv');
        $uploadedFile->moveTo($tempFile);

        try {
            $result = $this->importService->import($tempFile, $type, $realm, $pid);
        } finally {
            @unlink($tempFile);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'activeTab' => 'import',
            'result' => $result,
            'type' => $type,
            'realm' => $realm,
        ]);
        return $moduleTemplate->renderResponse('Upload');
    }

    public function listOrgunitsAction(ServerRequestInterface $request): ResponseInterface
    {
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $connection = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_orgunit');

        $totalCount = (int)$connection->executeQuery(
            'SELECT COUNT(*) FROM tx_thwovssp_domain_model_orgunit WHERE deleted = 0'
        )->fetchOne();

        $orgunits = $connection->executeQuery(
            'SELECT * FROM tx_thwovssp_domain_model_orgunit WHERE deleted = 0 ORDER BY name ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
        )->fetchAllAssociative();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'activeTab' => 'orgunits',
            'orgunits' => $orgunits,
            'totalCount' => $totalCount,
            'pagination' => $this->buildPagination($page, $perPage, $totalCount, 'admin_thwovssp_import.list-orgunits'),
        ]);
        return $moduleTemplate->renderResponse('ListOrgunits');
    }

    public function listDirectoriesAction(ServerRequestInterface $request): ResponseInterface
    {
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $connection = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_directory');

        $totalCount = (int)$connection->executeQuery(
            'SELECT COUNT(*) FROM tx_thwovssp_domain_model_directory WHERE deleted = 0'
        )->fetchOne();

        $directories = $connection->executeQuery(
            'SELECT * FROM tx_thwovssp_domain_model_directory WHERE deleted = 0 ORDER BY CASE WHEN sort_key IS NULL THEN 1 ELSE 0 END, sort_key ASC, name ASC LIMIT ' . $perPage . ' OFFSET ' . $offset
        )->fetchAllAssociative();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'activeTab' => 'directories',
            'directories' => $directories,
            'totalCount' => $totalCount,
            'pagination' => $this->buildPagination($page, $perPage, $totalCount, 'admin_thwovssp_import.list-directories'),
        ]);
        return $moduleTemplate->renderResponse('ListDirectories');
    }

    public function listUsersAction(ServerRequestInterface $request): ResponseInterface
    {
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $connection = $this->connectionPool->getConnectionForTable('fe_users');

        $totalCount = (int)$connection->executeQuery(
            'SELECT COUNT(*) FROM fe_users WHERE deleted = 0 AND thw_uid > 0'
        )->fetchOne();

        $users = $connection->executeQuery(
            'SELECT u.*, o.name AS orgunit_name, o.oe_code
             FROM fe_users u
             LEFT JOIN tx_thwovssp_domain_model_orgunit o ON u.thw_orgunit = o.uid
             WHERE u.deleted = 0 AND u.thw_uid > 0
             ORDER BY u.last_name ASC, u.first_name ASC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        )->fetchAllAssociative();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'activeTab' => 'users',
            'users' => $users,
            'totalCount' => $totalCount,
            'pagination' => $this->buildPagination($page, $perPage, $totalCount, 'admin_thwovssp_import.list-users'),
        ]);
        return $moduleTemplate->renderResponse('ListUsers');
    }

    /**
     * @return array{currentPage: int, totalPages: int, perPage: int, route: string, pages: array}
     */
    private function buildPagination(int $currentPage, int $perPage, int $totalCount, string $route): array
    {
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        $currentPage = min($currentPage, $totalPages);

        $pages = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i <= 2 || $i >= $totalPages - 1 || abs($i - $currentPage) <= 2) {
                $pages[] = ['number' => $i, 'isCurrent' => $i === $currentPage];
            } elseif (end($pages) !== null && ($pages[array_key_last($pages)]['number'] ?? 0) !== -1) {
                $pages[] = ['number' => -1, 'isCurrent' => false]; // ellipsis marker
            }
        }

        return [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'route' => $route,
            'pages' => $pages,
            'hasPrev' => $currentPage > 1,
            'hasNext' => $currentPage < $totalPages,
            'prevPage' => $currentPage - 1,
            'nextPage' => $currentPage + 1,
        ];
    }
}
