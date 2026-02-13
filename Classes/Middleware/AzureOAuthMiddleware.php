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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

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

    /**
     * @var AzureOAuthService
     */
    private $azureOAuthService;

    public function __construct(
        ?AzureOAuthService $azureOAuthService = null
    ) {
        $this->azureOAuthService = $azureOAuthService
            ?? GeneralUtility::makeInstance(AzureOAuthService::class);
    }

    private function appendParam(string $url, string $key, string $value): string
    {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . rawurlencode($key) . '=' . rawurlencode($value);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Resolve site context for per-site configuration
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $this->azureOAuthService->setSiteRootPageId($site->getRootPageId());
        }

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

        // Restore config context from state (for backend callbacks where site attribute may not be set)
        $configUid = (int)($state['configUid'] ?? 0);
        if ($configUid > 0) {
            $this->azureOAuthService->setConfigUid($configUid);
        }
        $siteRootPageId = (int)($state['siteRootPageId'] ?? 0);
        if ($siteRootPageId > 0) {
            $this->azureOAuthService->setSiteRootPageId($siteRootPageId);
        }

        $loginType = $state['type'] ?? 'frontend';
        $returnUrl = $state['returnUrl'] ?? '/';
        $this->logger->debug('Azure OAuth state valid', ['loginType' => $loginType, 'returnUrl' => $returnUrl]);

        // Exchange authorization code for user info
        try {
            $userInfo = $this->azureOAuthService->exchangeCodeForUserInfo($code, $loginType);
        } catch (\Throwable $e) {
            $this->logger->debug('Azure OAuth token exchange failed', ['exception' => $e->getMessage()]);
            return new RedirectResponse($this->appendParam($returnUrl, 'azure_login_error', 'exchange_failed'), 303);
        }

        $this->logger->debug('Azure OAuth user info received', ['email' => $userInfo['email'] ?? '']);

        // Store Azure user data as request attribute for the auth service
        $request = $request->withAttribute('azure_login_user', $userInfo);

        // Determine application type and inject login trigger
        $isBackend = (defined('TYPO3_MODE') && TYPO3_MODE === 'BE') || $loginType === 'backend';

        if ($isBackend) {
            // Backend: handle auth entirely here to avoid die() in TYPO3's backend chain.
            // TYPO3's BackendUserAuthenticator calls backendCheckLogin() which uses
            // HttpUtility::redirect() + die() for flow control, preventing our middleware
            // from ever receiving the response. Also, start() sets the session cookie via
            // PHP's header() function (not PSR-7), so it would not be in the PSR-7 response.

            // Inject login trigger fields into $_POST (read by GeneralUtility::_POST())
            $_POST['login_status'] = 'login';
            $_POST['userident'] = 'azure-oauth';
            $_POST['username'] = $userInfo['email'];

            // Update the global request so auth services can read our attributes
            $GLOBALS['TYPO3_REQUEST'] = $request;

            // Create and initialize the backend user — triggers the full auth chain
            $backendUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
            $GLOBALS['BE_USER'] = $backendUser;
            $backendUser->start();

            if (empty($backendUser->user['uid'])) {
                $this->logger->debug('Azure OAuth: BE login failed after start()');
                return new RedirectResponse(
                    $this->appendParam($returnUrl, 'azure_login_error', 'auth_failed'),
                    303
                );
            }

            $this->logger->debug('Azure OAuth: BE login succeeded', [
                'uid' => $backendUser->user['uid'],
                'username' => $backendUser->user['username'] ?? '',
            ]);

            // Build redirect response, carrying the session cookie with SameSite=Lax.
            // start() set the cookie via PHP header(); capture it and move into PSR-7.
            $redirect = new RedirectResponse($returnUrl, 303);
            foreach (headers_list() as $rawHeader) {
                if (stripos($rawHeader, 'Set-Cookie:') === 0) {
                    $cookieValue = ltrim(substr($rawHeader, strlen('Set-Cookie:')));
                    $cookieValue = preg_replace('/SameSite=Strict/i', 'SameSite=Lax', $cookieValue);
                    $redirect = $redirect->withAddedHeader('Set-Cookie', $cookieValue);
                }
            }
            header_remove('Set-Cookie');

            $this->logger->debug('Azure OAuth BE redirect', [
                'returnUrl' => $returnUrl,
            ]);
            return $redirect;
        }

        // Frontend: handle auth directly to avoid the page resolver.
        // The callback URL (/azure-login/callback) is not a real TYPO3 page,
        // so passing to $handler->handle() would trigger PageNotFoundException.
        // Instead, we create the FE user session ourselves (mirroring the BE approach).

        // Inject login trigger fields into $_POST (read by GeneralUtility::_POST())
        $_POST['logintype'] = 'login';
        $_POST['user'] = $userInfo['email'];
        $_POST['pass'] = 'azure-oauth';

        // Update the global request so auth services can read our attributes
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $this->logger->debug('Azure OAuth: starting FE auth directly', ['user' => $userInfo['email']]);

        // Create and initialise the frontend user — triggers the full auth chain
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->start();
        $frontendUser->unpack_uc();

        // Store in TSFE for backwards-compatibility
        if (isset($GLOBALS['TSFE'])) {
            $GLOBALS['TSFE']->fe_user = $frontendUser;
        }

        // Register the frontend user as Context aspect
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('frontend.user', GeneralUtility::makeInstance(UserAspect::class, $frontendUser));

        // Check if frontend login actually succeeded
        $isLoggedIn = $context->getPropertyFromAspect('frontend.user', 'isLoggedIn');
        if (!$isLoggedIn) {
            $this->logger->debug('Azure OAuth: FE login failed', ['email' => $userInfo['email'] ?? '']);
            $returnUrl = $this->appendParam($returnUrl, 'azure_login_error', 'auth_failed');
        } else {
            $this->logger->debug('Azure OAuth: FE login succeeded', [
                'uid' => $frontendUser->user['uid'] ?? null,
                'username' => $frontendUser->user['username'] ?? '',
            ]);
            $returnUrl = $this->appendParam($returnUrl, 'azure_login_success', '1');
        }

        // Build redirect response, carrying the session cookie.
        // start() sets the cookie via PHP header(); capture it and move into PSR-7.
        $redirect = new RedirectResponse($returnUrl, 303);
        foreach (headers_list() as $rawHeader) {
            if (stripos($rawHeader, 'Set-Cookie:') === 0) {
                $cookieValue = ltrim(substr($rawHeader, strlen('Set-Cookie:')));
                $redirect = $redirect->withAddedHeader('Set-Cookie', $cookieValue);
            }
        }
        header_remove('Set-Cookie');

        $this->logger->debug('Azure OAuth FE redirect', [
            'returnUrl' => $returnUrl,
        ]);
        return $redirect;
    }
}
