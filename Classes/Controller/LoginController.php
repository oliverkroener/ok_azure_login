<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller;

use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class LoginController extends ActionController
{
    public function __construct(
        private readonly AzureOAuthService $azureOAuthService,
    ) {}

    public function showAction(): ResponseInterface
    {
        $site = $this->request->getAttribute('site');
        if ($site instanceof Site) {
            $this->azureOAuthService->setSiteRootPageId($site->getRootPageId());
        }

        if (!$this->azureOAuthService->isConfigured('frontend')) {
            $this->view->assign('configurationError', true);
            return $this->htmlResponse();
        }

        $returnUrl = $this->request->getAttribute('normalizedParams')?->getRequestUri() ?? '/';
        $authorizeUrl = $this->azureOAuthService->buildAuthorizeUrl('frontend', $returnUrl);

        $this->view->assign('authorizeUrl', $authorizeUrl);

        $context = GeneralUtility::makeInstance(Context::class);
        $isLoggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');
        $this->view->assign('isLoggedIn', $isLoggedIn);

        $queryParams = $this->request->getQueryParams();
        $parsedBody = $this->request->getParsedBody();
        $isLogout = !$isLoggedIn && ($parsedBody['logintype'] ?? '') === 'logout';
        if ($isLogout) {
            $this->view->assign('logoutSuccess', true);
        } elseif (($queryParams['azure_login_success'] ?? '') !== '') {
            $this->view->assign('loginSuccess', true);
        } else {
            $errorCode = $queryParams['azure_login_error'] ?? '';
            if ($errorCode !== '') {
                $this->view->assign('loginError', $errorCode);
            }
        }

        return $this->htmlResponse();
    }
}
