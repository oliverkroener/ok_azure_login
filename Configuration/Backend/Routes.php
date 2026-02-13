<?php

use OliverKroener\OkAzureLogin\Controller\Backend\AzureCallbackController;
use OliverKroener\OkAzureLogin\Controller\Backend\ConfigurationController;

return [
    'azure_login_callback' => [
        'path' => '/azure-login/callback',
        'access' => 'public',
        'target' => AzureCallbackController::class . '::handleCallback',
    ],
    'web_okazurelogin_save' => [
        'path' => '/module/web/azure-login/save',
        'target' => ConfigurationController::class . '::saveAction',
    ],
    'web_okazurelogin_backendList' => [
        'path' => '/module/web/azure-login/backend-list',
        'target' => ConfigurationController::class . '::backendListAction',
    ],
    'web_okazurelogin_backendEdit' => [
        'path' => '/module/web/azure-login/backend-edit',
        'target' => ConfigurationController::class . '::backendEditAction',
    ],
    'web_okazurelogin_backendSave' => [
        'path' => '/module/web/azure-login/backend-save',
        'target' => ConfigurationController::class . '::backendSaveAction',
    ],
    'web_okazurelogin_backendDelete' => [
        'path' => '/module/web/azure-login/backend-delete',
        'target' => ConfigurationController::class . '::backendDeleteAction',
    ],
];
