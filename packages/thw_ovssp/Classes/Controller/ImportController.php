<?php

declare(strict_types=1);

namespace Init\Thw\Ovssp\Controller;

use Init\Thw\Ovssp\Service\ImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

class ImportController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ImportService $importService,
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->createView($request, 'Index');
        $html = $view->render();
        return new HtmlResponse($this->wrapInModuleBody($request, $html));
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
            $view = $this->createView($request, 'Index');
            $view->assignMultiple([
                'errors' => $errors,
                'selectedType' => $type,
                'selectedRealm' => $realm,
                'pid' => $pid,
            ]);
            $html = $view->render();
            return new HtmlResponse($this->wrapInModuleBody($request, $html));
        }

        $tempFile = GeneralUtility::tempnam('ovssp_import_', '.csv');
        $uploadedFile->moveTo($tempFile);

        try {
            $result = $this->importService->import($tempFile, $type, $realm, $pid);
        } finally {
            @unlink($tempFile);
        }

        $view = $this->createView($request, 'Upload');
        $view->assignMultiple([
            'result' => $result,
            'type' => $type,
            'realm' => $realm,
        ]);
        $html = $view->render();
        return new HtmlResponse($this->wrapInModuleBody($request, $html));
    }

    private function createView(ServerRequestInterface $request, string $template): \TYPO3\CMS\Core\View\ViewInterface
    {
        $viewData = new ViewFactoryData(
            templatePathAndFilename: 'EXT:thw_ovssp/Resources/Private/Templates/' . $template . '.html',
            request: $request,
        );
        return $this->viewFactory->create($viewData);
    }

    private function wrapInModuleBody(ServerRequestInterface $request, string $body): string
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assign('content', $body);
        // Use render() to get the module chrome as a string
        return $moduleTemplate->render();
    }
}
