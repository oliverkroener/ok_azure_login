<?php

use OliverKroener\OkAzureLogin\Controller\Backend\ConfigurationController;

return [
    'web_okazurelogin' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'admin',
        'path' => '/module/web/azure-login',
        'iconIdentifier' => 'ext-ok-azure-login-microsoft',
        'labels' => 'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_be_module.xlf',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => ConfigurationController::class . '::editAction',
            ],
            'save' => [
                'target' => ConfigurationController::class . '::saveAction',
                'methods' => ['POST'],
            ],
            'backendList' => [
                'target' => ConfigurationController::class . '::backendListAction',
            ],
            'backendEdit' => [
                'target' => ConfigurationController::class . '::backendEditAction',
            ],
            'backendSave' => [
                'target' => ConfigurationController::class . '::backendSaveAction',
                'methods' => ['POST'],
            ],
            'backendDelete' => [
                'target' => ConfigurationController::class . '::backendDeleteAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
