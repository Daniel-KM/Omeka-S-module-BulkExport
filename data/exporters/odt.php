<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'OpenDocument text (odt)', // @translate
    'formatter' => 'odt',
    'config' => [
        'formatter' => [
            'format_fields' => 'label',
            'resource_types' => [
                'o:Item',
            ],
            'metadata' => null,
            'query' => '',
        ],
    ],
];
