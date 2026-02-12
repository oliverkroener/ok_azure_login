<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-ok-azure-login' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ok_azure_login/Resources/Public/Icons/Extension.svg',
    ],
    'ext-ok-azure-login-microsoft' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:ok_azure_login/Resources/Public/Icons/MicrosoftLogo.svg',
    ],
];
