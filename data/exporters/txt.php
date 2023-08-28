<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'Text', // @translate
    'writer' => \BulkExport\Writer\TextWriter::class,
    'config' => [
        'writer' => [
            'format_fields' => 'label',
            'resource_types' => [
                'o:Item',
            ],
            'metadata' => null,
            'query' => '',
        ],
    ],
];
