<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Security\RequestToken;

/**
 * When an Azure OAuth callback is in progress, provide a valid RequestToken
 * so TYPO3's CSRF protection does not block the login request.
 */
class AzureRequestTokenListener
{
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        $request = $event->getRequest();
        $azureUser = $request->getAttribute('azure_login_user');

        if ($azureUser === null) {
            return;
        }

        $user = $event->getUser();
        $scope = 'core/user-auth/' . strtolower($user->loginType);
        $event->setRequestToken(RequestToken::create($scope));
    }
}
