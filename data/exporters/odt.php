<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'OpenDocument text (odt)', // @translate
    'writer' => \BulkExport\Writer\OpenDocumentTextWriter::class,
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
