<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\CsvWriterConfigForm;

/**
 * CSV Writer - thin wrapper around Csv Formatter.
 *
 * This writer delegates CSV formatting to the Csv Formatter while handling:
 * - Configuration forms for admin interface
 * - Incremental export support
 * - HistoryLog integration (include_deleted)
 * - File path management
 * - Job integration
 *
 * @see \BulkExport\Formatter\Csv for the actual CSV formatting logic
 */
class CsvWriter extends AbstractFormatterWriter
{
    const DEFAULT_DELIMITER = ',';
    const DEFAULT_ENCLOSURE = '"';
    const DEFAULT_ESCAPE = '\\';

    protected $label = 'CSV'; // @translate
    protected $extension = 'csv';
    protected $mediaType = 'text/csv';
    protected $configFormClass = CsvWriterConfigForm::class;
    protected $paramsFormClass = CsvWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'csv';

    protected $configKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
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
        'value_per_column',
        'column_metadata',
    ];

    protected $paramsKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
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
        'value_per_column',
        'column_metadata',
    ];

    protected function getFormatterOptions(): array
    {
        $options = parent::getFormatterOptions();

        // Add CSV-specific options.
        $options['delimiter'] = $this->getParam('delimiter', self::DEFAULT_DELIMITER);
        $options['enclosure'] = $this->getParam('enclosure', self::DEFAULT_ENCLOSURE);
        $options['escape'] = $this->getParam('escape', self::DEFAULT_ESCAPE);

        return $options;
    }
}
