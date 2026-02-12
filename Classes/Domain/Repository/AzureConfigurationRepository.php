<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Domain\Repository;

use OliverKroener\OkAzureLogin\Service\EncryptionService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class AzureConfigurationRepository
{
    private const TABLE = 'tx_okazurelogin_configuration';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly EncryptionService $encryptionService,
    ) {}

    /**
     * Find a frontend config by site root page ID (first matching record).
     */
    public function findBySiteRootPageId(int $siteRootPageId): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq(
                'site_root_page_id',
                $qb->createNamedParameter($siteRootPageId, Connection::PARAM_INT)
            ))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->mapRowToConfig($row);
    }

    /**
     * Find a single config by uid.
     */
    public function findByUid(int $uid): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        $config = $this->mapRowToConfig($row);
        $config['uid'] = (int)$row['uid'];
        return $config;
    }

    /**
     * Find all records with valid backend credentials.
     *
     * @return list<array{uid: int, backendLoginLabel: string, tenantId: string, clientId: string, clientSecret: string, redirectUriBackend: string, siteRootPageId: int}>
     */
    public function findAllConfiguredForBackend(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $rows = $qb->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq('enabled', $qb->createNamedParameter(1, Connection::PARAM_INT)),
                $qb->expr()->neq('tenant_id', $qb->createNamedParameter('')),
                $qb->expr()->neq('client_id', $qb->createNamedParameter('')),
                $qb->expr()->neq('client_secret_encrypted', $qb->createNamedParameter('')),
                $qb->expr()->neq('redirect_uri_backend', $qb->createNamedParameter(''))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $config = $this->mapRowToConfig($row);
            $config['uid'] = (int)$row['uid'];
            $config['siteRootPageId'] = (int)$row['site_root_page_id'];
            $result[] = $config;
        }
        return $result;
    }

    /**
     * Count all backend configs (records with site_root_page_id = 0).
     */
    public function countBackendConfigs(): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        return (int)$qb->count('uid')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('site_root_page_id', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Paginated list of backend configs (records with site_root_page_id = 0).
     *
     * @return list<array{uid: int, backendLoginLabel: string, tenantId: string, clientId: string, hasSecret: bool, redirectUriBackend: string}>
     */
    public function findBackendConfigsPaginated(int $limit, int $offset): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $rows = $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('site_root_page_id', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('uid', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'uid' => (int)$row['uid'],
                'enabled' => (bool)($row['enabled'] ?? true),
                'showLabel' => (bool)($row['show_label'] ?? true),
                'backendLoginLabel' => $row['backend_login_label'] ?? '',
                'tenantId' => $row['tenant_id'] ?? '',
                'clientId' => $row['client_id'] ?? '',
                'hasSecret' => !empty($row['client_secret_encrypted']),
                'redirectUriBackend' => $row['redirect_uri_backend'] ?? '',
            ];
        }
        return $result;
    }

    /**
     * Upsert by site_root_page_id (used for frontend configs).
     */
    public function save(int $siteRootPageId, array $data): void
    {
        $existing = $this->findRaw($siteRootPageId);

        $fields = [
            'site_root_page_id' => $siteRootPageId,
            'backend_login_label' => $data['backendLoginLabel'] ?? '',
            'tenant_id' => $data['tenantId'] ?? '',
            'client_id' => $data['clientId'] ?? '',
            'redirect_uri_frontend' => $data['redirectUriFrontend'] ?? '',
            'redirect_uri_backend' => $data['redirectUriBackend'] ?? '',
            'tstamp' => time(),
        ];

        $clientSecret = $data['clientSecret'] ?? '';
        if ($clientSecret !== '') {
            $fields['client_secret_encrypted'] = $this->encryptionService->encrypt($clientSecret);
        }

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);

        if ($existing === null) {
            $fields['crdate'] = time();
            if ($clientSecret === '') {
                $fields['client_secret_encrypted'] = '';
            }
            $connection->insert(self::TABLE, $fields);
        } else {
            $connection->update(self::TABLE, $fields, ['site_root_page_id' => $siteRootPageId]);
        }
    }

    /**
     * Insert or update a backend config by uid.
     */
    public function saveBackendConfig(?int $uid, array $data): int
    {
        $fields = [
            'site_root_page_id' => 0,
            'enabled' => (int)($data['enabled'] ?? true),
            'show_label' => (int)($data['showLabel'] ?? true),
            'backend_login_label' => $data['backendLoginLabel'] ?? '',
            'tenant_id' => $data['tenantId'] ?? '',
            'client_id' => $data['clientId'] ?? '',
            'redirect_uri_frontend' => '',
            'redirect_uri_backend' => $data['redirectUriBackend'] ?? '',
            'tstamp' => time(),
        ];

        $clientSecret = $data['clientSecret'] ?? '';
        if ($clientSecret !== '') {
            $fields['client_secret_encrypted'] = $this->encryptionService->encrypt($clientSecret);
        }

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);

        if ($uid === null || $uid === 0) {
            $fields['crdate'] = time();
            if ($clientSecret === '') {
                $fields['client_secret_encrypted'] = '';
            }
            $connection->insert(self::TABLE, $fields);
            return (int)$connection->lastInsertId(self::TABLE);
        }

        $connection->update(self::TABLE, $fields, ['uid' => $uid]);
        return $uid;
    }

    /**
     * Copy the encrypted client secret from one config to the record matching a site root page ID.
     */
    public function cloneEncryptedSecret(int $sourceUid, int $targetSiteRootPageId): void
    {
        $encryptedSecret = $this->getEncryptedSecret($sourceUid);
        if ($encryptedSecret !== null) {
            $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
            $connection->update(
                self::TABLE,
                ['client_secret_encrypted' => $encryptedSecret],
                ['site_root_page_id' => $targetSiteRootPageId]
            );
        }
    }

    /**
     * Copy the encrypted client secret from one config to another by uid.
     */
    public function cloneEncryptedSecretByUid(int $sourceUid, int $targetUid): void
    {
        $encryptedSecret = $this->getEncryptedSecret($sourceUid);
        if ($encryptedSecret !== null) {
            $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
            $connection->update(
                self::TABLE,
                ['client_secret_encrypted' => $encryptedSecret],
                ['uid' => $targetUid]
            );
        }
    }

    private function getEncryptedSecret(int $uid): ?string
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $encryptedSecret = $qb->select('client_secret_encrypted')
            ->from(self::TABLE)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        if ($encryptedSecret !== false && $encryptedSecret !== '') {
            return $encryptedSecret;
        }
        return null;
    }

    /**
     * Delete a record by uid.
     */
    public function deleteByUid(int $uid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->delete(self::TABLE, ['uid' => $uid]);
    }

    private function findRaw(int $siteRootPageId): ?array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $qb->getRestrictions()->removeAll();
        $row = $qb->select('*')
            ->from(self::TABLE)
            ->where($qb->expr()->eq(
                'site_root_page_id',
                $qb->createNamedParameter($siteRootPageId, Connection::PARAM_INT)
            ))
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    private function mapRowToConfig(array $row): array
    {
        $clientSecret = '';
        if (!empty($row['client_secret_encrypted'])) {
            $clientSecret = $this->encryptionService->decrypt($row['client_secret_encrypted']);
        }

        return [
            'enabled' => (bool)($row['enabled'] ?? true),
            'showLabel' => (bool)($row['show_label'] ?? true),
            'tenantId' => $row['tenant_id'] ?? '',
            'clientId' => $row['client_id'] ?? '',
            'clientSecret' => $clientSecret,
            'redirectUriFrontend' => $row['redirect_uri_frontend'] ?? '',
            'redirectUriBackend' => $row['redirect_uri_backend'] ?? '',
            'backendLoginLabel' => $row['backend_login_label'] ?? '',
        ];
    }
}
