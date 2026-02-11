<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Middleware;

use OliverKroener\OkAzureLogin\Service\AzureOAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RedirectResponse;

/**
 * Intercepts OAuth callbacks from Microsoft Entra ID.
 *
 * Registered in both frontend and backend middleware stacks, before their
 * respective authentication middlewares. When a `code` query parameter is
 * present together with a valid `state`, the middleware:
 *
 * 1. Exchanges the authorization code for user info via Microsoft Graph
 * 2. Stores the Azure user data as a request attribute
 * 3. Injects the appropriate login trigger field for TYPO3's auth chain
 * 4. After the auth chain processes, redirects to the return URL
 */
class AzureOAuthMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AzureOAuthService $azureOAuthService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? null;
        $stateParam = $queryParams['state'] ?? null;

        if ($code === null || $stateParam === null) {
            return $handler->handle($request);
        }

        $this->logger->debug('Azure OAuth callback start', [
            'uri' => (string)$request->getUri(),
            'applicationType' => $request->getAttribute('applicationType'),
        ]);

        // Validate the HMAC-signed state
        $state = $this->azureOAuthService->validateState($stateParam);
        if ($state === null) {
            $this->logger->debug('Azure OAuth state validation failed');
            return $handler->handle($request);
        }

        $loginType = $state['type'] ?? 'frontend';
        $returnUrl = $state['returnUrl'] ?? '/';
        $this->logger->debug('Azure OAuth state valid', ['loginType' => $loginType, 'returnUrl' => $returnUrl]);

        // Exchange authorization code for user info
        try {
            $userInfo = $this->azureOAuthService->exchangeCodeForUserInfo($code, $loginType);
        } catch (\Throwable $e) {
            $this->logger->debug('Azure OAuth token exchange failed', ['exception' => $e->getMessage()]);
            return $handler->handle($request);
        }

        $this->logger->debug('Azure OAuth user info received', ['email' => $userInfo['email'] ?? '']);

        // Store Azure user data as request attribute for the auth service
        $request = $request->withAttribute('azure_login_user', $userInfo);

        // Determine application type and inject login trigger
        $applicationType = $request->getAttribute('applicationType', 'frontend');

        if ($applicationType === 'backend' || $loginType === 'backend') {
            // Backend: inject login_status=login to trigger BE auth chain
            $body = $request->getParsedBody() ?? [];
            $body['login_status'] = 'login';
            $body['userident'] = 'azure-oauth';
            $body['username'] = $userInfo['email'];
            $request = $request->withParsedBody($body);
            $this->logger->debug('Injected BE login fields', ['username' => $userInfo['email']]);
        } else {
            // Frontend: inject logintype=login to trigger FE auth chain
            $body = $request->getParsedBody() ?? [];
            $body['logintype'] = 'login';
            $body['user'] = $userInfo['email'];
            $body['pass'] = 'azure-oauth';
            $request = $request->withParsedBody($body);
            $this->logger->debug('Injected FE login fields', ['user' => $userInfo['email']]);
        }

        // Update the global request so auth services can read our attributes
        $GLOBALS['TYPO3_REQUEST'] = $request;

        // Pass to the next middleware (which includes TYPO3's auth middleware)
        $response = $handler->handle($request);

        // Preserve Set-Cookie headers from the auth chain response.
        // TYPO3 defaults to SameSite=Strict for BE cookies, but after a cross-site
        // redirect from Microsoft, the browser won't send Strict cookies on the
        // subsequent same-site navigation. Downgrade to Lax for this response only.
        // Note: FE cookies default to SameSite=Lax, so this is a no-op for frontend.
        // If FE.cookieSameSite is ever changed to "strict", the same issue will occur.
        $redirect = new RedirectResponse($returnUrl, 303);
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            $cookie = preg_replace('/SameSite=Strict/i', 'SameSite=Lax', $cookie);
            $redirect = $redirect->withAddedHeader('Set-Cookie', $cookie);
        }

        $this->logger->debug('Azure OAuth redirect', [
            'returnUrl' => $returnUrl,
            'cookiesCarried' => count($response->getHeader('Set-Cookie')),
        ]);
        return $redirect;
    }
}
