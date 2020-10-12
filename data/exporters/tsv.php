<?php declare(strict_types=1);
return [
    'owner' => null,
    'label' => 'TSV (tab-separated values)', // @translate
    'writerClass' => \BulkExport\Writer\TsvWriter::class,
    'writerConfig' => [
        'separator' => ' | ',
        'resource_types' => [
            'o:Item',
        ],
        'metadata' => null,
        'query' => '',
    ],
];
