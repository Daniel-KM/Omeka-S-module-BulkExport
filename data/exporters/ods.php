<?php declare(strict_types=1);

return [
    'owner' => null,
    'label' => 'OpenDocument spreadsheet (ods)', // @translate
    'writer' => \BulkExport\Writer\OpenDocumentSpreadsheetWriter::class,
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
