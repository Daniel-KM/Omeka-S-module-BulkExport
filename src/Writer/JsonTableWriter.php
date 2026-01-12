<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\FieldsJsonWriterConfigForm;

/**
 * Json Table Writer - thin wrapper around JsonTable Formatter.
 *
 * @see \BulkExport\Formatter\JsonTable for the actual formatting logic
 */
class JsonTableWriter extends AbstractFormatterWriter
{
    protected $label = 'Json Table'; // @translate
    protected $extension = 'table.json';
    protected $mediaType = 'application/json';
    protected $configFormClass = FieldsJsonWriterConfigForm::class;
    protected $paramsFormClass = FieldsJsonWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'json-table';

    protected $configKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
    ];

    protected $paramsKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
    ];
}
