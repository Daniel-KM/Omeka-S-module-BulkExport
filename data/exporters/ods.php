<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'OpenDocument spreadsheet (ods)', // @translate
    'formatter' => 'ods',
    'config' => [
        'formatter' => [
            'separator' => ' | ',
            'resource_types' => [
                'o:Item',
            ],
            'metadata' => null,
            'query' => '',
        ],
    ],
];
