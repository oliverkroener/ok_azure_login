<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\LoginProvider;

use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Backend login provider that shows a "Sign in with Microsoft" button
 * on the TYPO3 backend login screen.
 *
 * Implements render() for TYPO3 v12 and modifyView() for v13+.
 */
class AzureLoginProvider implements LoginProviderInterface
{
    public function __construct(
        private readonly AzureOAuthService $azureOAuthService,
    ) {}

    /**
     * TYPO3 v12 compatibility: render the login form using StandaloneView.
     */
    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        $authorizeUrl = $this->azureOAuthService->buildAuthorizeUrl('backend', '/typo3');
        $view->assign('authorizeUrl', $authorizeUrl);
        $view->setTemplatePathAndFilename(
            'EXT:ok_azure_login/Resources/Private/Templates/Login/AzureLoginForm.html'
        );
    }

    /**
     * TYPO3 v13+: modify the view and return the template identifier.
     */
    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $authorizeUrl = $this->azureOAuthService->buildAuthorizeUrl('backend', '/typo3');
        $view->assign('authorizeUrl', $authorizeUrl);

        // Add extension template paths so the login form template can be resolved
        $templatePaths = $view->getRenderingContext()->getTemplatePaths();
        $currentPaths = $templatePaths->getTemplateRootPaths();
        $currentPaths[] = 'EXT:ok_azure_login/Resources/Private/Templates';
        $templatePaths->setTemplateRootPaths($currentPaths);

        return 'Login/AzureLoginForm';
    }
}
