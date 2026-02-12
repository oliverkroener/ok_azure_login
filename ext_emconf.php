<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Azure Login',
    'description' => 'A TYPO3 extension to login using Microsoft Entra and MSGraph API.',
    'category' => 'plugin',
    'author' => 'Oliver Kroener',
    'author_email' => 'ok@oliver-kroener.de',
    'author_company' => 'Oliver Kroener',
    'state' => 'stable',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
