<?php declare(strict_types=1);
return [
    'owner' => null,
    'label' => 'CSV', // @translate
    'writerClass' => \BulkExport\Writer\CsvWriter::class,
    'writerConfig' => [
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
];
