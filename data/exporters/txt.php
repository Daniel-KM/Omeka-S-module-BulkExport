<?php declare(strict_types=1);
return [
    'owner' => null,
    'label' => 'Text', // @translate
    'writerClass' => \BulkExport\Writer\TextWriter::class,
    'writerConfig' => [
        'format_fields' => 'label',
        'resource_types' => [
            'o:Item',
        ],
        'metadata' => null,
        'query' => '',
    ],
];
