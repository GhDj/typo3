<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Controller;

use Init\Thw\Ovssp\Service\ImportService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class ImportController extends ActionController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ImportService $importService,
    ) {
        // parent constructor is not needed in v14 ActionController
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        return $moduleTemplate->renderResponse('Import/Index');
    }

    public function uploadAction(): ResponseInterface
    {
        $type = (string)($this->request->getArgument('type') ?? '');
        $realm = strtoupper((string)($this->request->getArgument('realm') ?? ''));
        $pid = (int)($this->request->getArgument('pid') ?? 0);

        $errors = [];

        if (!in_array($type, ['users', 'orgunits', 'directories'], true)) {
            $errors[] = 'Please select a valid import type.';
        }

        if ($type === 'users' && !in_array($realm, ['HA', 'EA'], true)) {
            $errors[] = 'AD Realm (HA or EA) is required for user imports.';
        }

        // Handle file upload via $_FILES (Extbase does not map file uploads automatically)
        $fileData = $_FILES['tx_thwovssp_import']['tmp_name']['importFile'] ?? null;
        $fileError = $_FILES['tx_thwovssp_import']['error']['importFile'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileData === null || (int)$fileError !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload a valid CSV file.';
        }

        if (!empty($errors)) {
            $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
            $moduleTemplate->assignMultiple([
                'errors' => $errors,
                'selectedType' => $type,
                'selectedRealm' => $realm,
                'pid' => $pid,
            ]);
            return $moduleTemplate->renderResponse('Import/Index');
        }

        // Copy to a stable temp file for processing
        $tempFile = GeneralUtility::tempnam('ovssp_import_', '.csv');
        move_uploaded_file($fileData, $tempFile);

        try {
            $result = $this->importService->import($tempFile, $type, $realm, $pid);
        } finally {
            @unlink($tempFile);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assignMultiple([
            'result' => $result,
            'type' => $type,
            'realm' => $realm,
        ]);
        return $moduleTemplate->renderResponse('Import/Result');
    }
}
