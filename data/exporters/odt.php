<?php
return [
    'owner' => null,
    'label' => 'OpenDocument text (odt)', // @translate
    'writerClass' => \BulkExport\Writer\OpenDocumentTextWriter::class,
    'writerConfig' => [
        'format_fields' => 'label',
        'resource_types' => [
            'o:Item',
        ],
        'metadata' => null,
        'query' => '',
    ],
];
