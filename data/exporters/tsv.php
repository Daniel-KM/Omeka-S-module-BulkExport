<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'TSV (tab-separated values)', // @translate
    'formatter' => 'tsv',
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
