<?php

declare(strict_types=1);

defined('TYPO3_MODE') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Frontend plugin registration (FE-only — backend login uses the login provider)
ExtensionUtility::registerPlugin(
    'OliverKroener.OkAzureLogin',
    'Login',
    'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_db.xlf:tx_okazurelogin.name',
    'ext-ok-azure-login-microsoft'
);

$loginPluginSignature = 'okazurelogin_login';

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$loginPluginSignature] = 'pi_flexform';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$loginPluginSignature] = 'layout,select_key,pages,recursive';

ExtensionManagementUtility::addPiFlexFormValue(
    $loginPluginSignature,
    'FILE:EXT:ok_azure_login/Configuration/FlexForms/Login.xml'
);

// Frontend logout plugin registration
ExtensionUtility::registerPlugin(
    'OliverKroener.OkAzureLogin',
    'Logout',
    'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang_db.xlf:tx_okazurelogin_logout.name',
    'ext-ok-azure-login-microsoft'
);

$logoutPluginSignature = 'okazurelogin_logout';

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$logoutPluginSignature] = 'pi_flexform';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$logoutPluginSignature] = 'layout,select_key,pages,recursive';

ExtensionManagementUtility::addPiFlexFormValue(
    $logoutPluginSignature,
    'FILE:EXT:ok_azure_login/Configuration/FlexForms/Logout.xml'
);

ExtensionManagementUtility::addStaticFile(
    'ok_azure_login',
    'Configuration/TypoScript',
    'Azure Login'
);
