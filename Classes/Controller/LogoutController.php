<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller;

use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class LogoutController extends ActionController
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
        $context = GeneralUtility::makeInstance(Context::class);
        $isLoggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');
        $this->view->assign('isLoggedIn', $isLoggedIn);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function logoutAction(): void
    {
        $feUser = $GLOBALS['TSFE']->fe_user;
        if ($feUser instanceof FrontendUserAuthentication) {
            $feUser->logoff();
        }

        $typo3Request = $GLOBALS['TYPO3_REQUEST'];
        $normalizedParams = $typo3Request->getAttribute('normalizedParams');
        $redirectUrl = !empty($this->settings['redirectUrl'])
            ? $this->settings['redirectUrl']
            : ($normalizedParams !== null ? $normalizedParams->getSiteUrl() : '/');

        if (!empty($this->settings['microsoftSignOut'])) {
            $site = $typo3Request->getAttribute('site');
            if ($site instanceof Site) {
                $this->azureOAuthService->setSiteRootPageId($site->getRootPageId());
            }
            $config = $this->azureOAuthService->getConfiguration('frontend');
            $redirectUrl = sprintf(
                'https://login.microsoftonline.com/%s/oauth2/v2.0/logout?post_logout_redirect_uri=%s',
                rawurlencode($config['tenantId']),
                rawurlencode($redirectUrl)
            );
        }

        $this->redirectToUri($redirectUrl);
    }
}
