<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'CSV', // @translate
    'formatter' => 'csv',
    'config' => [
        'formatter' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'separator' => ' | ',
            'resource_types' => [
                'o:Item',
            ],
            'metadata' => null,
            'query' => '',
        ],
    ],
];
