<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\FieldsJsonWriterConfigForm;

/**
 * GeoJSON Writer - thin wrapper around GeoJson Formatter.
 *
 * @see \BulkExport\Formatter\GeoJson for the actual GeoJSON formatting logic
 */
class GeoJsonWriter extends AbstractFormatterWriter
{
    protected $label = 'GeoJSON'; // @translate
    protected $extension = 'geojson';
    protected $mediaType = 'application/geo+json';
    protected $configFormClass = FieldsJsonWriterConfigForm::class;
    protected $paramsFormClass = FieldsJsonWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'geojson';

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
