<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Service;

use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\AuthorizationCodeContext;
use OliverKroener\OkAzureLogin\Domain\Repository\AzureConfigurationRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AzureOAuthService
{
    private const STATE_TTL = 600; // 10 minutes

    private int $siteRootPageId = 0;
    private int $configUid = 0;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly AzureConfigurationRepository $configurationRepository,
    ) {}

    public function setSiteRootPageId(int $siteRootPageId): void
    {
        $this->siteRootPageId = $siteRootPageId;
    }

    public function setConfigUid(int $configUid): void
    {
        $this->configUid = $configUid;
    }

    public function isConfigured(string $loginType = 'frontend'): bool
    {
        $config = $this->getConfiguration($loginType);
        if ($loginType === 'backend') {
            // Backend redirect URI is derived from route config, no manual field needed
            return ($config['tenantId'] ?? '') !== ''
                && ($config['clientId'] ?? '') !== ''
                && ($config['clientSecret'] ?? '') !== '';
        }

        return ($config['tenantId'] ?? '') !== ''
            && ($config['clientId'] ?? '') !== ''
            && ($config['clientSecret'] ?? '') !== ''
            && ($config['redirectUriFrontend'] ?? '') !== '';
    }

    public function buildAuthorizeUrl(string $loginType, string $returnUrl): string
    {
        $config = $this->getConfiguration($loginType);
        $tenantId = $config['tenantId'];
        $clientId = $config['clientId'];

        $redirectUri = $loginType === 'backend'
            ? $this->getBackendCallbackUrl()
            : $config['redirectUriFrontend'];

        $state = $this->createState($loginType, $returnUrl);

        $params = [
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => 'openid profile User.Read',
            'state' => $state,
        ];

        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize?%s',
            rawurlencode($tenantId),
            http_build_query($params)
        );
    }

    /**
     * @return array{email: string, displayName: string, givenName: string, surname: string}
     */
    public function exchangeCodeForUserInfo(string $code, string $loginType): array
    {
        $config = $this->getConfiguration($loginType);

        $redirectUri = $loginType === 'backend'
            ? $this->getBackendCallbackUrl()
            : $config['redirectUriFrontend'];

        $tokenContext = new AuthorizationCodeContext(
            $config['tenantId'],
            $config['clientId'],
            $config['clientSecret'],
            $code,
            $redirectUri
        );

        $graphClient = new GraphServiceClient($tokenContext, ['User.Read']);
        $me = $graphClient->me()->get()->wait();

        return [
            'email' => $me->getMail() ?? $me->getUserPrincipalName(),
            'displayName' => $me->getDisplayName() ?? '',
            'givenName' => $me->getGivenName() ?? '',
            'surname' => $me->getSurname() ?? '',
        ];
    }

    public function createState(string $type, string $returnUrl): string
    {
        $payload = [
            'type' => $type,
            'returnUrl' => $returnUrl,
            'siteRootPageId' => $this->siteRootPageId,
            'configUid' => $this->configUid,
            'nonce' => bin2hex(random_bytes(16)),
            'exp' => time() + self::STATE_TTL,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $json, $this->getEncryptionKey());

        return base64_encode($signature . '|' . $json);
    }

    /**
     * @return array{type: string, returnUrl: string, siteRootPageId: int, nonce: string, exp: int}|null
     */
    public function validateState(string $signedState): ?array
    {
        $decoded = base64_decode($signedState, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$signature, $json] = $parts;

        $expectedSignature = hash_hmac('sha256', $json, $this->getEncryptionKey());
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * @return array{tenantId: string, clientId: string, clientSecret: string, redirectUriFrontend: string, redirectUriBackend: string, backendLoginLabel: string}
     */
    public function getConfiguration(string $loginType = 'frontend'): array
    {
        // Backend config: try by uid first, then by siteRootPageId, then page 0, then ext_conf
        if ($loginType === 'backend') {
            if ($this->configUid > 0) {
                $dbConfig = $this->configurationRepository->findByUid($this->configUid);
                if ($dbConfig !== null && ($dbConfig['tenantId'] ?? '') !== '') {
                    return $dbConfig;
                }
            }
            if ($this->siteRootPageId > 0) {
                $dbConfig = $this->configurationRepository->findBySiteRootPageId($this->siteRootPageId);
                if ($dbConfig !== null && ($dbConfig['tenantId'] ?? '') !== '') {
                    return $dbConfig;
                }
            }
            $dbConfig = $this->configurationRepository->findBySiteRootPageId(0);
            if ($dbConfig !== null && ($dbConfig['tenantId'] ?? '') !== '') {
                return $dbConfig;
            }
            return (array)$this->extensionConfiguration->get('ok_azure_login');
        }

        // Frontend config: per-site
        if ($this->siteRootPageId > 0) {
            $dbConfig = $this->configurationRepository->findBySiteRootPageId($this->siteRootPageId);
            if ($dbConfig !== null && ($dbConfig['tenantId'] ?? '') !== '') {
                return $dbConfig;
            }
        }

        // Fallback to extension configuration
        return (array)$this->extensionConfiguration->get('ok_azure_login');
    }

    /**
     * Build the backend callback URL from the registered backend route.
     */
    private function getBackendCallbackUrl(): string
    {
        $uriBuilder = GeneralUtility::makeInstance(BackendUriBuilder::class);
        return (string)$uriBuilder->buildUriFromRoute(
            'azure_login_callback',
            [],
            BackendUriBuilder::ABSOLUTE_URL
        );
    }

    private function getEncryptionKey(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
    }
}
