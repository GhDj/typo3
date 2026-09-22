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
        $connection = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_orgunit');
        $result = $connection->executeQuery(
            'SELECT * FROM tx_thwovssp_domain_model_orgunit WHERE deleted = 0 ORDER BY name ASC'
        );
        $orgunits = $result->fetchAllAssociative();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'activeTab' => 'orgunits',
            'orgunits' => $orgunits,
            'totalCount' => count($orgunits),
        ]);
        return $moduleTemplate->renderResponse('ListOrgunits');
    }

    public function listDirectoriesAction(ServerRequestInterface $request): ResponseInterface
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_thwovssp_domain_model_directory');
        $result = $connection->executeQuery(
            'SELECT * FROM tx_thwovssp_domain_model_directory WHERE deleted = 0 ORDER BY CASE WHEN sort_key IS NULL THEN 1 ELSE 0 END, sort_key ASC, name ASC'
        );
        $directories = $result->fetchAllAssociative();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'activeTab' => 'directories',
            'directories' => $directories,
            'totalCount' => count($directories),
        ]);
        return $moduleTemplate->renderResponse('ListDirectories');
    }

    public function listUsersAction(ServerRequestInterface $request): ResponseInterface
    {
        $connection = $this->connectionPool->getConnectionForTable('fe_users');
        $result = $connection->executeQuery(
            'SELECT u.*, o.name AS orgunit_name, o.oe_code
             FROM fe_users u
             LEFT JOIN tx_thwovssp_domain_model_orgunit o ON u.thw_orgunit = o.uid
             WHERE u.deleted = 0 AND u.thw_uid > 0
             ORDER BY u.last_name ASC, u.first_name ASC
             LIMIT 500'
        );
        $users = $result->fetchAllAssociative();

        $totalResult = $connection->executeQuery(
            'SELECT COUNT(*) as cnt FROM fe_users WHERE deleted = 0 AND thw_uid > 0'
        );
        $totalCount = (int)$totalResult->fetchOne();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'activeTab' => 'users',
            'users' => $users,
            'totalCount' => $totalCount,
            'isLimited' => $totalCount > 500,
        ]);
        return $moduleTemplate->renderResponse('ListUsers');
    }
}
