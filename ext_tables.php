<?php

declare(strict_types=1);

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
    'web',
    'okazurelogin',
    'after:info',
    null,
    [
        'routeTarget' => \OliverKroener\OkAzureLogin\Controller\Backend\ConfigurationController::class . '::handleRequest',
        'access' => 'admin',
        'name' => 'web_okazurelogin',
        'iconIdentifier' => 'ext-ok-azure-login-microsoft',
        'labels' => 'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_be_module.xlf',
        'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
    ]
);
