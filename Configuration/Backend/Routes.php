<?php

use OliverKroener\OkAzureLogin\Controller\Backend\AzureCallbackController;

return [
    'azure_login_callback' => [
        'path' => '/azure-login/callback',
        'access' => 'public',
        'target' => AzureCallbackController::class . '::handleCallback',
    ],
];
