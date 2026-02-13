<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Service;

use GuzzleHttp\Client;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\User;
use OliverKroener\OkAzureLogin\Domain\Repository\AzureConfigurationRepository;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AzureOAuthService
{
    private const STATE_TTL = 600; // 10 minutes

    /**
     * @var int
     */
    private $siteRootPageId = 0;

    /**
     * @var int
     */
    private $configUid = 0;

    /**
     * @var ExtensionConfiguration
     */
    private $extensionConfiguration;

    /**
     * @var AzureConfigurationRepository
     */
    private $configurationRepository;

    public function __construct(
        ?ExtensionConfiguration $extensionConfiguration = null,
        ?AzureConfigurationRepository $configurationRepository = null
    ) {
        $this->extensionConfiguration = $extensionConfiguration
            ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->configurationRepository = $configurationRepository
            ?? GeneralUtility::makeInstance(AzureConfigurationRepository::class);
    }

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
        $redirectUri = $loginType === 'backend'
            ? ($config['redirectUriBackend'] ?? '')
            : ($config['redirectUriFrontend'] ?? '');

        return ($config['tenantId'] ?? '') !== ''
            && ($config['clientId'] ?? '') !== ''
            && ($config['clientSecret'] ?? '') !== ''
            && $redirectUri !== '';
    }

    public function buildAuthorizeUrl(string $loginType, string $returnUrl): string
    {
        $config = $this->getConfiguration($loginType);
        $tenantId = $config['tenantId'];
        $clientId = $config['clientId'];

        $redirectUri = $loginType === 'backend'
            ? $config['redirectUriBackend']
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
     * @return array{email: string, displayName: string}
     */
    public function exchangeCodeForUserInfo(string $code, string $loginType): array
    {
        $config = $this->getConfiguration($loginType);

        $redirectUri = $loginType === 'backend'
            ? $config['redirectUriBackend']
            : $config['redirectUriFrontend'];

        // Exchange authorization code for access token
        $client = new Client();
        $tokenResponse = $client->post(
            sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $config['tenantId']),
            [
                'form_params' => [
                    'client_id' => $config['clientId'],
                    'client_secret' => $config['clientSecret'],
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'scope' => 'openid profile User.Read',
                ],
            ]
        );

        $tokenData = json_decode((string)$tokenResponse->getBody(), true);
        $accessToken = $tokenData['access_token'];

        // Use Graph SDK v1 to get user profile
        $graph = new Graph();
        $graph->setAccessToken($accessToken);

        /** @var User $me */
        $me = $graph->createRequest('GET', '/me')
            ->setReturnType(User::class)
            ->execute();

        return [
            'email' => $me->getMail() ?? $me->getUserPrincipalName(),
            'displayName' => $me->getDisplayName() ?? '',
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
        } catch (\JsonException $e) {
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

    private function getEncryptionKey(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
    }
}
