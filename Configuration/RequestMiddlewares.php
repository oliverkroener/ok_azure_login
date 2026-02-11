<?php

use OliverKroener\OkAzureLogin\Middleware\AzureOAuthMiddleware;

return [
    'frontend' => [
        'oliverkroener/azure-oauth' => [
            'target' => AzureOAuthMiddleware::class,
            'before' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
    'backend' => [
        'oliverkroener/azure-oauth' => [
            'target' => AzureOAuthMiddleware::class,
            'after' => [
                'typo3/cms-backend/backend-routing',
            ],
            'before' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];
