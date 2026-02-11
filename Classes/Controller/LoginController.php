<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller;

use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class LoginController extends ActionController
{
    public function __construct(
        private readonly AzureOAuthService $azureOAuthService,
    ) {}

    public function showAction(): ResponseInterface
    {
        $returnUrl = $this->request->getAttribute('normalizedParams')?->getRequestUri() ?? '/';
        $authorizeUrl = $this->azureOAuthService->buildAuthorizeUrl('frontend', $returnUrl);

        $this->view->assign('authorizeUrl', $authorizeUrl);

        return $this->htmlResponse();
    }
}
