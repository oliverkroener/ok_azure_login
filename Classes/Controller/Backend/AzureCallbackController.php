<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;

/**
 * Backend route handler for Azure OAuth callback.
 *
 * By the time this controller runs, the AzureOAuthMiddleware has already
 * processed the OAuth callback and the TYPO3 auth chain has authenticated
 * the user. This controller simply redirects to the backend.
 */
class AzureCallbackController
{
    public function handleCallback(ServerRequestInterface $request): ResponseInterface
    {
        return new RedirectResponse('/typo3', 303);
    }
}
