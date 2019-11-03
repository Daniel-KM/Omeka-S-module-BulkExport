<?php
return [
    'owner' => null,
    'label' => 'OpenDocument spreadsheet (ods)', // @translate
    'writerClass' => \BulkExport\Writer\OpenDocumentSpreadsheetWriter::class,
    'writerConfig' => [
        'separator' => ' | ',
        'resource_types' => [
            'o:Item',
        ],
        'metadata' => null,
        'query' => '',
    ],
];
