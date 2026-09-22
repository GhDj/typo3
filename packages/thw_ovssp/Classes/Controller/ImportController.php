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
    ) {}

    public function indexAction(): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($this->request);
        $view->assign('showForm', true);
        return $view->renderResponse('Index');
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

        $fileData = $_FILES['tx_thwovssp_import']['tmp_name']['importFile'] ?? null;
        $fileError = $_FILES['tx_thwovssp_import']['error']['importFile'] ?? UPLOAD_ERR_NO_FILE;

        if ($fileData === null || (int)$fileError !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload a valid CSV file.';
        }

        if (!empty($errors)) {
            $view = $this->moduleTemplateFactory->create($this->request);
            $view->assignMultiple([
                'showForm' => true,
                'errors' => $errors,
                'selectedType' => $type,
                'selectedRealm' => $realm,
                'pid' => $pid,
            ]);
            return $view->renderResponse('Index');
        }

        $tempFile = GeneralUtility::tempnam('ovssp_import_', '.csv');
        move_uploaded_file($fileData, $tempFile);

        try {
            $result = $this->importService->import($tempFile, $type, $realm, $pid);
        } finally {
            @unlink($tempFile);
        }

        $view = $this->moduleTemplateFactory->create($this->request);
        $view->assignMultiple([
            'result' => $result,
            'type' => $type,
            'realm' => $realm,
        ]);
        return $view->renderResponse('Upload');
    }
}
