<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller\Backend;

use OliverKroener\OkAzureLogin\Domain\Repository\AzureConfigurationRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ConfigurationController
{
    private const BACKEND_ITEMS_PER_PAGE = 20;

    private AzureConfigurationRepository $configurationRepository;
    private SiteFinder $siteFinder;
    private UriBuilder $uriBuilder;
    private IconFactory $iconFactory;

    public function __construct(
        ?AzureConfigurationRepository $configurationRepository = null,
        ?SiteFinder $siteFinder = null,
        ?UriBuilder $uriBuilder = null,
        ?IconFactory $iconFactory = null,
    ) {
        $this->configurationRepository = $configurationRepository
            ?? GeneralUtility::getContainer()->get(AzureConfigurationRepository::class);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->uriBuilder = $uriBuilder ?? GeneralUtility::makeInstance(UriBuilder::class);
        $this->iconFactory = $iconFactory ?? GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * Entry point for the module's main route (routeTarget from ext_tables.php).
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        return $this->editAction($request);
    }

    // -- Frontend config (per-site) -------------------------------------------

    public function editAction(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $context = $this->resolveContext($request->getQueryParams()['context'] ?? 'frontend');
        $moduleTemplate = $this->createModuleTemplate($request);

        $languageService = $this->getLanguageService();

        $pageInfo = BackendUtility::readPageAccess(
            $id,
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];

        if ($pageInfo !== []) {
            $moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageInfo);
        }

        if ($id === 0 || $context === 'backend') {
            return new RedirectResponse(
                (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin_backendList', ['id' => $id])
            );
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($id);
            $siteRootPageId = $site->getRootPageId();
        } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
            $view = $this->createView('Backend/Configuration/Edit');
            $view->assign('noSiteFound', true);
            $moduleTemplate->setContent($view->render());
            return new HtmlResponse($moduleTemplate->renderContent());
        }

        $encryptionKeyMissing = empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);

        $config = $this->configurationRepository->findBySiteRootPageId($siteRootPageId);
        $hasExistingSecret = false;
        if ($config !== null) {
            $hasExistingSecret = ($config['clientSecret'] ?? '') !== '';
            $config['clientSecret'] = '';
        }

        $saveUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin_save',
            ['id' => $id, 'context' => 'frontend']
        );

        $frontendUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin',
            ['id' => $id, 'context' => 'frontend']
        );
        $backendUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin_backendList',
            ['id' => $id]
        );

        $this->configureDocHeader($moduleTemplate);
        $this->loadFormDirtyCheckAssets($languageService);

        $backendConfigs = $this->configurationRepository->findBackendConfigsPaginated(100, 0);

        if ($backendConfigs !== []) {
            GeneralUtility::makeInstance(PageRenderer::class)->loadRequireJsModule(
                'TYPO3/CMS/OkAzureLogin/Backend/CloneConfig'
            );
        }

        $view = $this->createView('Backend/Configuration/Edit');
        $view->assignMultiple([
            'config' => $config ?? [
                'tenantId' => '',
                'clientId' => '',
                'clientSecret' => '',
                'redirectUriFrontend' => '',
                'redirectUriBackend' => '',
                'backendLoginLabel' => '',
            ],
            'siteRootPageId' => $siteRootPageId,
            'siteIdentifier' => $site->getIdentifier(),
            'hasExistingSecret' => $hasExistingSecret,
            'encryptionKeyMissing' => $encryptionKeyMissing,
            'saveUrl' => $saveUrl,
            'context' => 'frontend',
            'frontendUrl' => $frontendUrl,
            'backendUrl' => $backendUrl,
            'backendConfigs' => $backendConfigs,
        ]);

        $moduleTemplate->setContent($view->render());
        return new HtmlResponse($moduleTemplate->renderContent());
    }

    public function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $id = (int)($request->getQueryParams()['id'] ?? $parsedBody['id'] ?? 0);
        $data = $parsedBody['data'] ?? [];

        $configPageId = 0;
        if ($id > 0) {
            try {
                $site = $this->siteFinder->getSiteByPageId($id);
                $configPageId = $site->getRootPageId();
            } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
                // no site found
            }
        }

        if ($configPageId > 0) {
            $this->configurationRepository->save($configPageId, [
                'tenantId' => trim((string)($data['tenantId'] ?? '')),
                'clientId' => trim((string)($data['clientId'] ?? '')),
                'clientSecret' => (string)($data['clientSecret'] ?? ''),
                'redirectUriFrontend' => trim((string)($data['redirectUriFrontend'] ?? '')),
                'redirectUriBackend' => '',
                'backendLoginLabel' => '',
            ]);

            $cloneSecretFromUid = (int)($data['cloneSecretFromUid'] ?? 0);
            if ($cloneSecretFromUid > 0 && ($data['clientSecret'] ?? '') === '') {
                $this->configurationRepository->cloneEncryptedSecret($cloneSecretFromUid, $configPageId);
            }

            $this->addFlashMessage('message.saved.title', 'message.saved.body', FlashMessage::OK);
        }

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin', ['id' => $id, 'context' => 'frontend'])
        );
    }

    // -- Backend configs (list/edit/save/delete) ------------------------------

    public function backendListAction(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $moduleTemplate = $this->createModuleTemplate($request);

        $languageService = $this->getLanguageService();

        $pageInfo = BackendUtility::readPageAccess(
            $id,
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];

        if ($pageInfo !== []) {
            $moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageInfo);
        }

        $encryptionKeyMissing = empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);

        $totalCount = $this->configurationRepository->countBackendConfigs();
        $limit = self::BACKEND_ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        $totalPages = max(1, (int)ceil($totalCount / $limit));
        $configs = $this->configurationRepository->findBackendConfigsPaginated($limit, $offset);

        $frontendUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin',
            ['id' => $id, 'context' => 'frontend']
        );
        $backendUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin_backendList',
            ['id' => $id]
        );
        $newUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin_backendEdit',
            ['id' => $id, 'configUid' => 0]
        );

        // Build pagination URLs
        $paginationUrls = [];
        for ($p = 1; $p <= $totalPages; $p++) {
            $paginationUrls[$p] = (string)$this->uriBuilder->buildUriFromRoute(
                'web_okazurelogin_backendList',
                ['id' => $id, 'page' => $p]
            );
        }

        // Build edit/delete URLs for each config
        foreach ($configs as &$config) {
            $config['editUrl'] = (string)$this->uriBuilder->buildUriFromRoute(
                'web_okazurelogin_backendEdit',
                ['id' => $id, 'configUid' => $config['uid']]
            );
            $config['deleteUrl'] = (string)$this->uriBuilder->buildUriFromRoute(
                'web_okazurelogin_backendDelete',
                ['id' => $id, 'configUid' => $config['uid']]
            );
        }
        unset($config);

        // Load delete confirmation JS module
        GeneralUtility::makeInstance(PageRenderer::class)->loadRequireJsModule(
            'TYPO3/CMS/OkAzureLogin/Backend/DeleteConfirm'
        );

        $view = $this->createView('Backend/Configuration/BackendList');
        $view->assignMultiple([
            'context' => 'backend',
            'frontendUrl' => $frontendUrl,
            'backendUrl' => $backendUrl,
            'newUrl' => $newUrl,
            'configs' => $configs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'paginationUrls' => $paginationUrls,
            'encryptionKeyMissing' => $encryptionKeyMissing,
            'id' => $id,
        ]);

        $moduleTemplate->setContent($view->render());
        return new HtmlResponse($moduleTemplate->renderContent());
    }

    public function backendEditAction(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $configUid = (int)($request->getQueryParams()['configUid'] ?? 0);
        $moduleTemplate = $this->createModuleTemplate($request);

        $languageService = $this->getLanguageService();

        $pageInfo = BackendUtility::readPageAccess(
            $id,
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];

        if ($pageInfo !== []) {
            $moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageInfo);
        }

        $encryptionKeyMissing = empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);

        $config = null;
        $hasExistingSecret = false;
        $isNew = ($configUid === 0);

        if (!$isNew) {
            $config = $this->configurationRepository->findByUid($configUid);
            if ($config !== null) {
                $hasExistingSecret = ($config['clientSecret'] ?? '') !== '';
                $config['clientSecret'] = '';
            }
        }

        $saveUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin_backendSave',
            ['id' => $id, 'configUid' => $configUid]
        );

        $listUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin_backendList',
            ['id' => $id]
        );

        $this->configureDocHeader($moduleTemplate);
        $this->loadFormDirtyCheckAssets($languageService);

        // Load other backend configs for the clone dropdown (exclude the current one)
        $otherConfigs = array_filter(
            $this->configurationRepository->findBackendConfigsPaginated(100, 0),
            static fn(array $c) => $c['uid'] !== $configUid
        );

        if ($otherConfigs !== []) {
            GeneralUtility::makeInstance(PageRenderer::class)->loadRequireJsModule(
                'TYPO3/CMS/OkAzureLogin/Backend/CloneBackendConfig'
            );
        }

        $view = $this->createView('Backend/Configuration/BackendEdit');
        $view->assignMultiple([
            'config' => $config ?? [
                'enabled' => true,
                'showLabel' => true,
                'tenantId' => '',
                'clientId' => '',
                'clientSecret' => '',
                'redirectUriBackend' => '',
                'backendLoginLabel' => '',
            ],
            'configUid' => $configUid,
            'isNew' => $isNew,
            'hasExistingSecret' => $hasExistingSecret,
            'encryptionKeyMissing' => $encryptionKeyMissing,
            'saveUrl' => $saveUrl,
            'listUrl' => $listUrl,
            'id' => $id,
            'otherConfigs' => array_values($otherConfigs),
        ]);

        $moduleTemplate->setContent($view->render());
        return new HtmlResponse($moduleTemplate->renderContent());
    }

    public function backendSaveAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $id = (int)($request->getQueryParams()['id'] ?? $parsedBody['id'] ?? 0);
        $configUid = (int)($request->getQueryParams()['configUid'] ?? $parsedBody['configUid'] ?? 0);
        $data = $parsedBody['data'] ?? [];

        $savedUid = $this->configurationRepository->saveBackendConfig(
            $configUid > 0 ? $configUid : null,
            [
                'enabled' => (bool)($data['enabled'] ?? false),
                'showLabel' => (bool)($data['showLabel'] ?? false),
                'tenantId' => trim((string)($data['tenantId'] ?? '')),
                'clientId' => trim((string)($data['clientId'] ?? '')),
                'clientSecret' => (string)($data['clientSecret'] ?? ''),
                'redirectUriBackend' => trim((string)($data['redirectUriBackend'] ?? '')),
                'backendLoginLabel' => trim((string)($data['backendLoginLabel'] ?? '')),
            ]
        );

        $cloneSecretFromUid = (int)($data['cloneSecretFromUid'] ?? 0);
        if ($cloneSecretFromUid > 0 && ($data['clientSecret'] ?? '') === '') {
            $this->configurationRepository->cloneEncryptedSecretByUid($cloneSecretFromUid, $savedUid);
        }

        $this->addFlashMessage('message.saved.title', 'message.saved.body', FlashMessage::OK);

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin_backendList', ['id' => $id])
        );
    }

    public function backendDeleteAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $id = (int)($request->getQueryParams()['id'] ?? $parsedBody['id'] ?? 0);
        $configUid = (int)($request->getQueryParams()['configUid'] ?? $parsedBody['configUid'] ?? 0);

        if ($configUid > 0) {
            $this->configurationRepository->deleteByUid($configUid);
            $this->addFlashMessage('message.deleted.title', 'message.deleted.body', FlashMessage::OK);
        }

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin_backendList', ['id' => $id])
        );
    }

    // -- Helpers --------------------------------------------------------------

    private function resolveContext(string $context): string
    {
        return in_array($context, ['frontend', 'backend'], true) ? $context : 'frontend';
    }

    private function createModuleTemplate(ServerRequestInterface $request): ModuleTemplate
    {
        return GeneralUtility::makeInstance(ModuleTemplate::class);
    }

    private function createView(string $templateName): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths(['EXT:ok_azure_login/Resources/Private/Templates/']);
        $view->setLayoutRootPaths(['EXT:ok_azure_login/Resources/Private/Layouts/']);
        $view->setPartialRootPaths(['EXT:ok_azure_login/Resources/Private/Partials/']);
        $view->setTemplate($templateName);
        return $view;
    }

    private function configureDocHeader(ModuleTemplate $moduleTemplate): void
    {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $saveButton = $buttonBar->makeInputButton()
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:rm.saveDoc'))
            ->setName('_savedok')
            ->setValue('1')
            ->setShowLabelText(true)
            ->setForm('azureConfigForm')
            ->setIcon($this->iconFactory->getIcon('actions-document-save', Icon::SIZE_SMALL));

        $buttonBar->addButton($saveButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
    }

    private function loadFormDirtyCheckAssets(LanguageService $languageService): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadRequireJsModule(
            'TYPO3/CMS/OkAzureLogin/Backend/FormDirtyCheck'
        );
        $pageRenderer->addInlineLanguageLabelArray([
            'label.confirm.close_without_save.title' => $languageService->sL(
                'LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:label.confirm.close_without_save.title'
            ),
            'label.confirm.close_without_save.content' => $languageService->sL(
                'LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:label.confirm.close_without_save.content'
            ),
            'buttons.confirm.close_without_save.yes' => $languageService->sL(
                'LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:buttons.confirm.close_without_save.yes'
            ),
            'buttons.confirm.close_without_save.no' => $languageService->sL(
                'LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:buttons.confirm.close_without_save.no'
            ),
            'buttons.confirm.save_and_close' => $languageService->sL(
                'LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:buttons.confirm.save_and_close'
            ),
        ]);
    }

    private function addFlashMessage(string $titleKey, string $bodyKey, int $severity): void
    {
        $languageService = $this->getLanguageService();
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $languageService->sL('LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_be_module.xlf:' . $bodyKey),
            $languageService->sL('LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_be_module.xlf:' . $titleKey),
            $severity,
            true
        );
        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
