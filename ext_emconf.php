<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'luxletter - Extended',
    'description' => 'Extend luxletter with more features to be compatible with the wishes of jweiland.net',
    'category' => 'plugin',
    'version' => '1.0.0',
    'author' => 'Stefan Froemken',
    'author_email' => 'sfroemken@jweiland.net',
    'author_company' => 'jweiland.net',
    'state' => 'stable',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.4.99',
            'luxletter' => ''
        ],
        'conflicts' => [],
        'suggests' => [
        ],
    ],
];
