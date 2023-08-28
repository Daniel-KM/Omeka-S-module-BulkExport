<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'CSV', // @translate
    'writer' => \BulkExport\Writer\CsvWriter::class,
    'config' => [
        'writer' => [
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
