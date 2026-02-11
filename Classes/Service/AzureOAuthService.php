<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Service;

use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\AuthorizationCodeContext;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class AzureOAuthService
{
    private const STATE_TTL = 600; // 10 minutes

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function buildAuthorizeUrl(string $loginType, string $returnUrl): string
    {
        $config = $this->getConfiguration();
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
        $config = $this->getConfiguration();

        $redirectUri = $loginType === 'backend'
            ? $config['redirectUriBackend']
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
        ];
    }

    public function createState(string $type, string $returnUrl): string
    {
        $payload = [
            'type' => $type,
            'returnUrl' => $returnUrl,
            'nonce' => bin2hex(random_bytes(16)),
            'exp' => time() + self::STATE_TTL,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $json, $this->getEncryptionKey());

        return base64_encode($signature . '|' . $json);
    }

    /**
     * @return array{type: string, returnUrl: string, nonce: string, exp: int}|null
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
     * @return array{tenantId: string, clientId: string, clientSecret: string, redirectUriFrontend: string, redirectUriBackend: string}
     */
    public function getConfiguration(): array
    {
        return (array)$this->extensionConfiguration->get('ok_azure_login');
    }

    private function getEncryptionKey(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
    }
}
