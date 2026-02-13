<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller;

use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class LoginController extends ActionController
{
    /**
     * @var AzureOAuthService
     */
    private $azureOAuthService;

    public function __construct(
        AzureOAuthService $azureOAuthService
    ) {
        $this->azureOAuthService = $azureOAuthService;
    }

    public function showAction(): void
    {
        $typo3Request = $GLOBALS['TYPO3_REQUEST'];

        $site = $typo3Request->getAttribute('site');
        if ($site instanceof Site) {
            $this->azureOAuthService->setSiteRootPageId($site->getRootPageId());
        }

        if (!$this->azureOAuthService->isConfigured('frontend')) {
            $this->view->assign('configurationError', true);
            return;
        }

        $normalizedParams = $typo3Request->getAttribute('normalizedParams');
        $returnUrl = $normalizedParams !== null ? $normalizedParams->getRequestUri() : '/';
        $authorizeUrl = $this->azureOAuthService->buildAuthorizeUrl('frontend', $returnUrl);

        $this->view->assign('authorizeUrl', $authorizeUrl);

        $context = GeneralUtility::makeInstance(Context::class);
        $isLoggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');
        $this->view->assign('isLoggedIn', $isLoggedIn);

        $queryParams = $typo3Request->getQueryParams();
        $parsedBody = $typo3Request->getParsedBody();
        $isLogout = !$isLoggedIn && ($parsedBody['logintype'] ?? '') === 'logout';
        if ($isLogout) {
            $this->view->assign('logoutSuccess', true);
        } elseif ($isLoggedIn && ($queryParams['azure_login_success'] ?? '') !== '') {
            $this->view->assign('loginSuccess', true);
        } elseif (!$isLoggedIn && ($queryParams['azure_login_error'] ?? '') !== '') {
            $this->view->assign('loginError', $queryParams['azure_login_error']);
        }
    }
}
