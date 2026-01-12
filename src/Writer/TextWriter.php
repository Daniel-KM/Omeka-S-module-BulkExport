<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\TextWriterConfigForm;

/**
 * Text Writer - thin wrapper around Txt Formatter.
 *
 * @see \BulkExport\Formatter\Txt for the actual text formatting logic
 */
class TextWriter extends AbstractFormatterWriter
{
    protected $label = 'Text'; // @translate
    protected $extension = 'txt';
    protected $mediaType = 'text/plain';
    protected $configFormClass = TextWriterConfigForm::class;
    protected $paramsFormClass = TextWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'txt';

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
