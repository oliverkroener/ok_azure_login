<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Authentication;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;

class AzureLoginAuthService extends AbstractAuthenticationService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Look up a user by the email address provided by the Azure OAuth middleware.
     *
     * @return array|false User record or false if not responsible
     */
    public function getUser()
    {
        $azureUser = $this->getAzureUserFromRequest();
        if ($azureUser === null) {
            return false;
        }

        $email = $azureUser['email'] ?? '';
        if ($email === '') {
            $this->logger->debug('Azure auth: email is empty');
            return false;
        }

        // $this->db_user is set by TYPO3 auth chain; contains 'table' (fe_users or be_users),
        // 'check_pid_clause', 'enable_clause', 'username_column', etc.
        $table = $this->db_user['table'];
        $emailField = 'email';
        $this->logger->debug('Azure auth: looking up user', ['email' => $email, 'table' => $table]);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        // Keep default restrictions (deleted, disabled, starttime, endtime)

        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $emailField,
                    $queryBuilder->createNamedParameter($email)
                )
            )
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            $this->logger->debug('Azure auth: no user found', ['email' => $email, 'table' => $table]);
            return false;
        }

        $this->logger->debug('Azure auth: user found', ['uid' => $row['uid'], 'username' => $row['username']]);
        return $row;
    }

    /**
     * Authenticate user found by getUser().
     *
     * @return int 200 = authenticated (stop chain), 100 = not responsible (continue chain)
     */
    public function authUser(array $user): int
    {
        $azureUser = $this->getAzureUserFromRequest();
        if ($azureUser === null) {
            return 100;
        }

        $this->logger->debug('Azure auth: authenticated', ['uid' => $user['uid']]);
        return 200;
    }

    /**
     * @return array{email: string, displayName: string}|null
     */
    private function getAzureUserFromRequest(): ?array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request === null) {
            return null;
        }

        return $request->getAttribute('azure_login_user');
    }

    private function getConnectionPool(): \TYPO3\CMS\Core\Database\ConnectionPool
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Database\ConnectionPool::class
        );
    }
}
