<?php

declare(strict_types=1);

defined('TYPO3') or die();

use OliverKroener\OkAzureLogin\Authentication\AzureLoginAuthService;
use OliverKroener\OkAzureLogin\Controller\LoginController;
use OliverKroener\OkAzureLogin\Controller\LogoutController;
use OliverKroener\OkAzureLogin\LoginProvider\AzureLoginProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Frontend plugin (FE-only)
ExtensionUtility::configurePlugin(
    'OkAzureLogin',
    'Login',
    [
        LoginController::class => 'show',
    ],
    [
        LoginController::class => 'show',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

// Frontend logout plugin (FE-only)
ExtensionUtility::configurePlugin(
    'OkAzureLogin',
    'Logout',
    [
        LogoutController::class => 'show,logout',
    ],
    [
        LogoutController::class => 'show,logout',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

// Authentication service for FE + BE
ExtensionManagementUtility::addService(
    'ok_azure_login',
    'auth',
    AzureLoginAuthService::class,
    [
        'title' => 'Azure Login Authentication',
        'description' => 'Authentication service for frontend and backend users using Microsoft Entra ID (Azure AD)',
        'subtype' => 'getUserFE,authUserFE,getUserBE,authUserBE',
        'available' => true,
        'priority' => 82,
        'quality' => 50,
        'os' => '',
        'exec' => '',
        'className' => AzureLoginAuthService::class,
    ]
);

// Register icon for the backend login provider
$iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
$iconRegistry->registerIcon(
    'ext-ok-azure-login',
    SvgIconProvider::class,
    ['source' => 'EXT:ok_azure_login/Resources/Public/Icons/Extension.svg']
);
$iconRegistry->registerIcon(
    'ext-ok-azure-login-microsoft',
    SvgIconProvider::class,
    ['source' => 'EXT:ok_azure_login/Resources/Public/Icons/MicrosoftLogo.svg']
);

// Backend login provider – replaces default UsernamePasswordLoginProvider
// since our template already includes both the Azure button and username/password fields.
unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1433416747]);
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders']['azure-login'] = [
    'provider' => AzureLoginProvider::class,
    'sorting' => 75,
    'iconIdentifier' => 'ext-ok-azure-login',
    'label' => 'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang.xlf:backendLogin.label',
];
