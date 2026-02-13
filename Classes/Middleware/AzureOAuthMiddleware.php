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
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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

    /**
     * @var ConnectionPool
     */
    private $connectionPool;

    public function __construct(
        ?AzureOAuthService $azureOAuthService = null,
        ?ConnectionPool $connectionPool = null
    ) {
        $this->azureOAuthService = $azureOAuthService
            ?? GeneralUtility::makeInstance(AzureOAuthService::class);
        $this->connectionPool = $connectionPool
            ?? GeneralUtility::makeInstance(ConnectionPool::class);
    }

    private function appendParam(string $url, string $key, string $value): string
    {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . rawurlencode($key) . '=' . rawurlencode($value);
    }

    /**
     * Remove azure_login_error / azure_login_success query params from a URL
     * so they don't accumulate across retry attempts.
     */
    private function stripLoginParams(string $url): string
    {
        $parts = parse_url($url);
        if (!isset($parts['query'])) {
            return $url;
        }
        parse_str($parts['query'], $params);
        unset($params['azure_login_error'], $params['azure_login_success']);
        $query = http_build_query($params);
        $base = ($parts['scheme'] ?? '') !== '' ? $parts['scheme'] . '://' . ($parts['host'] ?? '') : '';
        $base .= $parts['path'] ?? '/';
        return $query !== '' ? $base . '?' . $query : $base;
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
        $returnUrl = $this->stripLoginParams($state['returnUrl'] ?? '/');
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
            $errorCode = $this->handleFailedFrontendLogin($userInfo);
            $returnUrl = $this->appendParam($returnUrl, 'azure_login_error', $errorCode);
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

    /**
     * Handle a failed frontend login: auto-create a disabled fe_user if configured.
     *
     * @return string Error code for the redirect URL
     */
    private function handleFailedFrontendLogin(array $userInfo): string
    {
        $config = $this->azureOAuthService->getConfiguration('frontend');
        if (empty($config['autoCreateFeUser'])) {
            return 'auth_failed';
        }

        $email = $userInfo['email'] ?? '';
        if ($email === '') {
            return 'auth_failed';
        }

        // Check if a user with this email already exists (including disabled)
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $qb->getRestrictions()->removeAll();
        $existingUser = $qb->select('uid', 'disable')
            ->from('fe_users')
            ->where(
                $qb->expr()->eq('email', $qb->createNamedParameter($email)),
                $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);

        if ($existingUser !== false) {
            // User exists but is disabled (or login failed for another reason)
            $this->logger->debug('Azure OAuth: fe_user exists but login failed', [
                'email' => $email,
                'disable' => $existingUser['disable'] ?? 0,
            ]);
            return 'account_pending';
        }

        // Create a new disabled fe_user
        $storagePid = (int)($config['feUserStoragePid'] ?? 0);
        $defaultGroups = $config['defaultFeGroups'] ?? '';
        $now = time();

        $connection = $this->connectionPool->getConnectionForTable('fe_users');
        $connection->insert('fe_users', [
            'pid' => $storagePid,
            'username' => $email,
            'email' => $email,
            'name' => $userInfo['displayName'] ?? '',
            'first_name' => $userInfo['givenName'] ?? '',
            'last_name' => $userInfo['surname'] ?? '',
            'password' => GeneralUtility::makeInstance(Random::class)->generateRandomHexString(64),
            'usergroup' => $defaultGroups,
            'disable' => 1,
            'tstamp' => $now,
            'crdate' => $now,
        ]);

        $this->logger->debug('Azure OAuth: created disabled fe_user', [
            'email' => $email,
            'pid' => $storagePid,
            'usergroup' => $defaultGroups,
        ]);

        return 'account_pending';
    }
}
