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
        $tableName = 'tx_thwovssp_domain_model_orgunit';

        $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
        $qb->getRestrictions()->removeAll();
        $totalCount = (int)$qb
            ->count('*')
            ->from($tableName)
            ->where($qb->expr()->eq('deleted', 0))
            ->executeQuery()
            ->fetchOne();

        $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
        $qb->getRestrictions()->removeAll();
        $orgunits = $qb
            ->select('*')
            ->from($tableName)
            ->where($qb->expr()->eq('deleted', 0))
            ->orderBy('name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->executeQuery()
            ->fetchAllAssociative();

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
        $tableName = 'tx_thwovssp_domain_model_directory';

        $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
        $qb->getRestrictions()->removeAll();
        $totalCount = (int)$qb
            ->count('*')
            ->from($tableName)
            ->where($qb->expr()->eq('deleted', 0))
            ->executeQuery()
            ->fetchOne();

        $qb = $this->connectionPool->getQueryBuilderForTable($tableName);
        $qb->getRestrictions()->removeAll();
        $directories = $qb
            ->select('*')
            ->from($tableName)
            ->where($qb->expr()->eq('deleted', 0))
            ->addOrderBy('sort_key', 'ASC')
            ->addOrderBy('name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->executeQuery()
            ->fetchAllAssociative();

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

        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeAll();
        $totalCount = (int)$qb
            ->count('*')
            ->from('fe_users')
            ->where(
                $qb->expr()->eq('deleted', 0),
                $qb->expr()->gt('thw_uid', 0)
            )
            ->executeQuery()
            ->fetchOne();

        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeAll();
        $users = $qb
            ->select('u.*', 'o.name AS orgunit_name', 'o.oe_code')
            ->from('fe_users', 'u')
            ->leftJoin('u', 'tx_thwovssp_domain_model_orgunit', 'o', $qb->expr()->eq('u.thw_orgunit', 'o.uid'))
            ->where(
                $qb->expr()->eq('u.deleted', 0),
                $qb->expr()->gt('u.thw_uid', 0)
            )
            ->addOrderBy('u.last_name', 'ASC')
            ->addOrderBy('u.first_name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->executeQuery()
            ->fetchAllAssociative();

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
