<?php

defined('TYPO3_MODE') or die();

// Frontend plugin (FE-only)
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'OkAzureLogin',
    'Login',
    [
        \OliverKroener\OkAzureLogin\Controller\LoginController::class => 'show',
    ],
    [
        \OliverKroener\OkAzureLogin\Controller\LoginController::class => 'show',
    ],
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

// Frontend logout plugin (FE-only)
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'OkAzureLogin',
    'Logout',
    [
        \OliverKroener\OkAzureLogin\Controller\LogoutController::class => 'show,logout',
    ],
    [
        \OliverKroener\OkAzureLogin\Controller\LogoutController::class => 'show,logout',
    ],
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

// Authentication service for FE + BE
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    'ok_azure_login',
    'auth',
    \OliverKroener\OkAzureLogin\Authentication\AzureLoginAuthService::class,
    [
        'title' => 'Azure Login Authentication',
        'description' => 'Authentication service for frontend and backend users using Microsoft Entra ID (Azure AD)',
        'subtype' => 'getUserFE,authUserFE,getUserBE,authUserBE',
        'available' => true,
        'priority' => 82,
        'quality' => 50,
        'os' => '',
        'exec' => '',
        'className' => \OliverKroener\OkAzureLogin\Authentication\AzureLoginAuthService::class,
    ]
);

// Register icons
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Core\Imaging\IconRegistry::class
);
$iconRegistry->registerIcon(
    'ext-ok-azure-login',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:ok_azure_login/Resources/Public/Icons/Extension.svg']
);
$iconRegistry->registerIcon(
    'ext-ok-azure-login-microsoft',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:ok_azure_login/Resources/Public/Icons/MicrosoftLogo.svg']
);

// Exclude Azure OAuth query parameters from cHash calculation
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'code';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'state';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'azure_login_success';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'azure_login_error';

// Register page TSconfig for new content element wizard
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    '@import "EXT:ok_azure_login/Configuration/page.tsconfig"'
);

// Backend login provider – replaces default UsernamePasswordLoginProvider
// since our template already includes both the Azure button and username/password fields.
unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1433416747]);
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders']['azure-login'] = [
    'provider' => \OliverKroener\OkAzureLogin\LoginProvider\AzureLoginProvider::class,
    'sorting' => 75,
    'icon-class' => 'ext-ok-azure-login',
    'label' => 'LLL:EXT:ok_azure_login/Resources/Private/Language/locallang.xlf:backendLogin.label',
];
