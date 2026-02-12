<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller;

use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class LogoutController extends ActionController
{
    public function __construct(
        private readonly AzureOAuthService $azureOAuthService,
    ) {}

    public function showAction(): ResponseInterface
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $isLoggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');
        $this->view->assign('isLoggedIn', $isLoggedIn);

        return $this->htmlResponse();
    }

    public function logoutAction(): ResponseInterface
    {
        $feUser = $this->request->getAttribute('frontend.user');
        if ($feUser instanceof FrontendUserAuthentication) {
            $feUser->logoff();
        }

        $redirectUrl = !empty($this->settings['redirectUrl'])
            ? $this->settings['redirectUrl']
            : $this->request->getAttribute('normalizedParams')?->getSiteUrl() ?? '/';

        if (!empty($this->settings['microsoftSignOut'])) {
            $site = $this->request->getAttribute('site');
            if ($site instanceof Site) {
                $this->azureOAuthService->setSiteRootPageId($site->getRootPageId());
            }
            $config = $this->azureOAuthService->getConfiguration('frontend');
            return new RedirectResponse(sprintf(
                'https://login.microsoftonline.com/%s/oauth2/v2.0/logout?post_logout_redirect_uri=%s',
                rawurlencode($config['tenantId']),
                rawurlencode($redirectUrl)
            ));
        }

        return new RedirectResponse($redirectUrl);
    }
}
