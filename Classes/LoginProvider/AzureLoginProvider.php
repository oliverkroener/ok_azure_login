<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\LoginProvider;

use OliverKroener\OkAzureLogin\Domain\Repository\AzureConfigurationRepository;
use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Backend login provider that shows "Sign in with Microsoft" buttons
 * on the TYPO3 backend login screen.
 *
 * Each configured backend config gets its own button.
 * Resolves dependencies from DI container for TYPO3 v11 compat (makeInstance).
 */
class AzureLoginProvider implements LoginProviderInterface
{
    private AzureOAuthService $azureOAuthService;
    private AzureConfigurationRepository $configurationRepository;

    public function __construct(
        ?AzureOAuthService $azureOAuthService = null,
        ?AzureConfigurationRepository $configurationRepository = null,
    ) {
        $container = GeneralUtility::getContainer();
        $this->azureOAuthService = $azureOAuthService
            ?? $container->get(AzureOAuthService::class);
        $this->configurationRepository = $configurationRepository
            ?? $container->get(AzureConfigurationRepository::class);
    }

    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        $view->assign('azureLogins', $this->collectBackendLogins());
        $view->setTemplatePathAndFilename(
            'EXT:ok_azure_login/Resources/Private/Templates/Login/AzureLoginForm.html'
        );
    }

    /**
     * @return list<array{label: string, authorizeUrl: string}>
     */
    private function collectBackendLogins(): array
    {
        $logins = [];
        $backendConfigs = $this->configurationRepository->findAllConfiguredForBackend();

        foreach ($backendConfigs as $config) {
            $this->azureOAuthService->setConfigUid((int)$config['uid']);
            $this->azureOAuthService->setSiteRootPageId((int)($config['siteRootPageId'] ?? 0));

            $logins[] = [
                'label' => ($config['showLabel'] ?? true) ? ($config['backendLoginLabel'] ?? '') : '',
                'authorizeUrl' => $this->azureOAuthService->buildAuthorizeUrl('backend', '/typo3'),
            ];
        }

        return $logins;
    }
}
