<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Frontend plugin registration (FE-only — backend login uses the login provider)
ExtensionUtility::registerPlugin(
    'OkAzureLogin',
    'Login',
    'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_db.xlf:tx_okazurelogin.name',
    'EXT:ok_azure_login/Resources/Public/Icons/Extension.svg'
);

$cType = 'okazurelogin_login';

$GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] = '
    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
        --palette--;;general,
        --palette--;;headers,
        pi_flexform,
    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
        --palette--;;hidden,
        --palette--;;access,
';

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:ok_azure_login/Configuration/FlexForms/Login.xml',
    $cType
);

// Frontend logout plugin registration
ExtensionUtility::registerPlugin(
    'OkAzureLogin',
    'Logout',
    'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_db.xlf:tx_okazurelogin_logout.name',
    'EXT:ok_azure_login/Resources/Public/Icons/Extension.svg'
);

$logoutCType = 'okazurelogin_logout';

$GLOBALS['TCA']['tt_content']['types'][$logoutCType]['showitem'] = '
    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
        --palette--;;general,
        pi_flexform,
    --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
        --palette--;;hidden,
        --palette--;;access,
';

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:ok_azure_login/Configuration/FlexForms/Logout.xml',
    $logoutCType
);

ExtensionManagementUtility::addStaticFile(
    'ok_azure_login',
    'Configuration/TypoScript',
    'Azure Login'
);
