<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller\Backend;

use OliverKroener\OkAzureLogin\Domain\Repository\AzureConfigurationRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
class ConfigurationController
{
    private const BACKEND_ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AzureConfigurationRepository $configurationRepository,
        private readonly SiteFinder $siteFinder,
        private readonly UriBuilder $uriBuilder,
        private readonly IconFactory $iconFactory,
        private readonly ConnectionPool $connectionPool,
    ) {}

    // ── Frontend config (per-site) ───────────────────────────────────

    public function editAction(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $context = $this->resolveContext($request->getQueryParams()['context'] ?? 'frontend');
        $view = $this->moduleTemplateFactory->create($request);

        $languageService = $this->getLanguageService();
        $moduleTitle = $languageService->sL(
            'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_be_module.xlf:module.title'
        );

        $pageInfo = BackendUtility::readPageAccess(
            $id,
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];

        $view->setTitle($moduleTitle, $pageInfo['title'] ?? '');

        if ($pageInfo !== []) {
            $view->getDocHeaderComponent()->setMetaInformation($pageInfo);
        }

        if ($id === 0 || $context === 'backend') {
            return new RedirectResponse(
                (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin.backendList', ['id' => $id])
            );
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($id);
            $siteRootPageId = $site->getRootPageId();
        } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException) {
            $view->assign('noSiteFound', true);
            return $view->renderResponse('Backend/Configuration/Edit');
        }

        $encryptionKeyMissing = empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);

        $config = $this->configurationRepository->findBySiteRootPageId($siteRootPageId);
        $hasExistingSecret = false;
        if ($config !== null) {
            $hasExistingSecret = ($config['clientSecret'] ?? '') !== '';
            $config['clientSecret'] = '';
        }

        $saveUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin.save',
            ['id' => $id, 'context' => 'frontend']
        );

        $frontendUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin',
            ['id' => $id, 'context' => 'frontend']
        );
        $backendUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin.backendList',
            ['id' => $id]
        );

        $this->configureDocHeader($view);
        $this->loadFormDirtyCheckAssets($languageService);

        $backendConfigs = $this->configurationRepository->findBackendConfigsPaginated(100, 0);

        if ($backendConfigs !== []) {
            GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule(
                '@oliverkroener/ok-azure-login/backend/clone-config.js'
            );
        }

        $feGroups = $this->fetchFeGroups();
        $selectedFeGroupUids = array_filter(
            array_map('intval', explode(',', $config['defaultFeGroups'] ?? ''))
        );

        // Page browser for feUserStoragePid
        $elementBrowserUrl = (string)$this->uriBuilder->buildUriFromRoute('wizard_element_browser', [
            'mode' => 'db',
            'bparams' => 'feUserStoragePid|||pages|',
        ]);
        $currentPid = (int)($config['feUserStoragePid'] ?? 0);
        $feUserStoragePidTitle = '';
        if ($currentPid > 0) {
            $pageRow = BackendUtility::getRecord('pages', $currentPid, 'title');
            $feUserStoragePidTitle = $pageRow ? '[' . $currentPid . '] ' . $pageRow['title'] : '[' . $currentPid . ']';
        }

        GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule(
            '@oliverkroener/ok-azure-login/backend/page-browser.js'
        );

        $view->assignMultiple([
            'config' => $config ?? [
                'tenantId' => '',
                'clientId' => '',
                'clientSecret' => '',
                'redirectUriFrontend' => '',
                'redirectUriBackend' => '',
                'backendLoginLabel' => '',
                'autoCreateFeUser' => false,
                'defaultFeGroups' => '',
                'feUserStoragePid' => 0,
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
            'feGroups' => $feGroups,
            'selectedFeGroupUids' => $selectedFeGroupUids,
            'elementBrowserUrl' => $elementBrowserUrl,
            'feUserStoragePidTitle' => $feUserStoragePidTitle,
        ]);

        return $view->renderResponse('Backend/Configuration/Edit');
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
            } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException) {
                // no site found
            }
        }

        if ($configPageId > 0) {
            $defaultFeGroups = $data['defaultFeGroups'] ?? [];
            if (is_array($defaultFeGroups)) {
                $defaultFeGroups = implode(',', array_filter($defaultFeGroups));
            }

            $this->configurationRepository->save($configPageId, [
                'tenantId' => trim((string)($data['tenantId'] ?? '')),
                'clientId' => trim((string)($data['clientId'] ?? '')),
                'clientSecret' => (string)($data['clientSecret'] ?? ''),
                'redirectUriFrontend' => trim((string)($data['redirectUriFrontend'] ?? '')),
                'redirectUriBackend' => '',
                'backendLoginLabel' => '',
                'autoCreateFeUser' => (bool)($data['autoCreateFeUser'] ?? false),
                'defaultFeGroups' => (string)$defaultFeGroups,
                'feUserStoragePid' => (int)($data['feUserStoragePid'] ?? 0),
            ]);

            $cloneSecretFromUid = (int)($data['cloneSecretFromUid'] ?? 0);
            if ($cloneSecretFromUid > 0 && ($data['clientSecret'] ?? '') === '') {
                $this->configurationRepository->cloneEncryptedSecret($cloneSecretFromUid, $configPageId);
            }

            $this->addFlashMessage('message.saved.title', 'message.saved.body', ContextualFeedbackSeverity::OK);
        }

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin', ['id' => $id, 'context' => 'frontend'])
        );
    }

    // ── Backend configs (list/edit/save/delete) ──────────────────────

    public function backendListAction(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $page = max(1, (int)($request->getQueryParams()['page'] ?? 1));
        $view = $this->moduleTemplateFactory->create($request);

        $languageService = $this->getLanguageService();
        $moduleTitle = $languageService->sL(
            'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_be_module.xlf:module.title'
        );

        $pageInfo = BackendUtility::readPageAccess(
            $id,
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];

        $view->setTitle($moduleTitle, $pageInfo['title'] ?? '');

        if ($pageInfo !== []) {
            $view->getDocHeaderComponent()->setMetaInformation($pageInfo);
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
            'web_okazurelogin.backendList',
            ['id' => $id]
        );
        $newUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin.backendEdit',
            ['id' => $id, 'configUid' => 0]
        );

        // Build pagination URLs
        $paginationUrls = [];
        for ($p = 1; $p <= $totalPages; $p++) {
            $paginationUrls[$p] = (string)$this->uriBuilder->buildUriFromRoute(
                'web_okazurelogin.backendList',
                ['id' => $id, 'page' => $p]
            );
        }

        // Build edit/delete URLs for each config
        foreach ($configs as &$config) {
            $config['editUrl'] = (string)$this->uriBuilder->buildUriFromRoute(
                'web_okazurelogin.backendEdit',
                ['id' => $id, 'configUid' => $config['uid']]
            );
            $config['deleteUrl'] = (string)$this->uriBuilder->buildUriFromRoute(
                'web_okazurelogin.backendDelete',
                ['id' => $id, 'configUid' => $config['uid']]
            );
        }
        unset($config);

        // Load delete confirmation JS module
        GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule(
            '@oliverkroener/ok-azure-login/backend/delete-confirm.js'
        );

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

        return $view->renderResponse('Backend/Configuration/BackendList');
    }

    public function backendEditAction(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $configUid = (int)($request->getQueryParams()['configUid'] ?? 0);
        $view = $this->moduleTemplateFactory->create($request);

        $languageService = $this->getLanguageService();
        $moduleTitle = $languageService->sL(
            'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_be_module.xlf:module.title'
        );

        $pageInfo = BackendUtility::readPageAccess(
            $id,
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        ) ?: [];

        $view->setTitle($moduleTitle, $pageInfo['title'] ?? '');

        if ($pageInfo !== []) {
            $view->getDocHeaderComponent()->setMetaInformation($pageInfo);
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
            'web_okazurelogin.backendSave',
            ['id' => $id, 'configUid' => $configUid]
        );

        $listUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'web_okazurelogin.backendList',
            ['id' => $id]
        );

        $this->configureDocHeader($view);
        $this->loadFormDirtyCheckAssets($languageService);

        // Load other backend configs for the clone dropdown (exclude the current one)
        $otherConfigs = array_filter(
            $this->configurationRepository->findBackendConfigsPaginated(100, 0),
            static fn(array $c) => $c['uid'] !== $configUid
        );

        if ($otherConfigs !== []) {
            GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule(
                '@oliverkroener/ok-azure-login/backend/clone-backend-config.js'
            );
        }

        // Load copy-to-clipboard JS module
        GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule(
            '@oliverkroener/ok-azure-login/backend/copy-callback-url.js'
        );

        // Build backend callback URL from route config
        $backendCallbackUrl = (string)$this->uriBuilder->buildUriFromRoute(
            'azure_login_callback',
            [],
            \TYPO3\CMS\Backend\Routing\UriBuilder::ABSOLUTE_URL
        );

        $view->assignMultiple([
            'config' => $config ?? [
                'enabled' => true,
                'showLabel' => true,
                'tenantId' => '',
                'clientId' => '',
                'clientSecret' => '',
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
            'backendCallbackUrl' => $backendCallbackUrl,
        ]);

        return $view->renderResponse('Backend/Configuration/BackendEdit');
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
                'backendLoginLabel' => trim((string)($data['backendLoginLabel'] ?? '')),
            ]
        );

        $cloneSecretFromUid = (int)($data['cloneSecretFromUid'] ?? 0);
        if ($cloneSecretFromUid > 0 && ($data['clientSecret'] ?? '') === '') {
            $this->configurationRepository->cloneEncryptedSecretByUid($cloneSecretFromUid, $savedUid);
        }

        $this->addFlashMessage('message.saved.title', 'message.saved.body', ContextualFeedbackSeverity::OK);

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin.backendList', ['id' => $id])
        );
    }

    public function backendDeleteAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $id = (int)($request->getQueryParams()['id'] ?? $parsedBody['id'] ?? 0);
        $configUid = (int)($request->getQueryParams()['configUid'] ?? $parsedBody['configUid'] ?? 0);

        if ($configUid > 0) {
            $this->configurationRepository->deleteByUid($configUid);
            $this->addFlashMessage('message.deleted.title', 'message.deleted.body', ContextualFeedbackSeverity::OK);
        }

        return new RedirectResponse(
            (string)$this->uriBuilder->buildUriFromRoute('web_okazurelogin.backendList', ['id' => $id])
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function fetchFeGroups(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_groups');
        $rows = $qb->select('uid', 'title')
            ->from('fe_groups')
            ->orderBy('title', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'uid' => (int)$row['uid'],
                'title' => $row['title'],
            ];
        }
        return $result;
    }

    private function resolveContext(string $context): string
    {
        return in_array($context, ['frontend', 'backend'], true) ? $context : 'frontend';
    }

    private function configureDocHeader($view): void
    {
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();

        $saveButton = $buttonBar->makeInputButton()
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:rm.saveDoc'))
            ->setName('_savedok')
            ->setValue('1')
            ->setShowLabelText(true)
            ->setForm('azureConfigForm')
            ->setIcon($this->iconFactory->getIcon(
                'actions-document-save',
                class_exists(\TYPO3\CMS\Core\Imaging\IconSize::class)
                    ? \TYPO3\CMS\Core\Imaging\IconSize::SMALL
                    : \TYPO3\CMS\Core\Imaging\Icon::SIZE_SMALL
            ));

        $buttonBar->addButton($saveButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
    }

    private function loadFormDirtyCheckAssets(LanguageService $languageService): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule(
            '@oliverkroener/ok-azure-login/backend/form-dirty-check.js'
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

    private function addFlashMessage(string $titleKey, string $bodyKey, ContextualFeedbackSeverity $severity): void
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
