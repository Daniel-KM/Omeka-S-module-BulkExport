<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'TSV (tab-separated values)', // @translate
    'writer' => \BulkExport\Writer\TsvWriter::class,
    'config' => [
        'writer' => [
            'separator' => ' | ',
            'resource_types' => [
                'o:Item',
            ],
            'metadata' => null,
            'query' => '',
        ],
    ],
];
