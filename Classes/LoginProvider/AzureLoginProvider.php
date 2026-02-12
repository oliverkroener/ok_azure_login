<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\LoginProvider;

use OliverKroener\OkAzureLogin\Domain\Repository\AzureConfigurationRepository;
use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Backend login provider that shows "Sign in with Microsoft" buttons
 * on the TYPO3 backend login screen.
 *
 * Each configured backend config gets its own button.
 * Implements render() for TYPO3 v12 and modifyView() for v13+.
 */
class AzureLoginProvider implements LoginProviderInterface
{
    public function __construct(
        private readonly AzureOAuthService $azureOAuthService,
        private readonly AzureConfigurationRepository $configurationRepository,
    ) {}

    /**
     * TYPO3 v12 compatibility: render the login form using StandaloneView.
     */
    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        $view->assign('azureLogins', $this->collectBackendLogins());
        $view->setTemplatePathAndFilename(
            'EXT:ok_azure_login/Resources/Private/Templates/Login/AzureLoginForm.html'
        );
    }

    /**
     * TYPO3 v13+: modify the view and return the template identifier.
     */
    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $view->assign('azureLogins', $this->collectBackendLogins());

        // Add extension template paths so the login form template can be resolved
        $templatePaths = $view->getRenderingContext()->getTemplatePaths();
        $currentPaths = $templatePaths->getTemplateRootPaths();
        $currentPaths[] = 'EXT:ok_azure_login/Resources/Private/Templates';
        $templatePaths->setTemplateRootPaths($currentPaths);

        return 'Login/AzureLoginForm';
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
                'label' => $config['backendLoginLabel'] ?? '',
                'authorizeUrl' => $this->azureOAuthService->buildAuthorizeUrl('backend', '/typo3'),
            ];
        }

        return $logins;
    }
}
