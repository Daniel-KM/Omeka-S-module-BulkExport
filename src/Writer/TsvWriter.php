<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\SpreadsheetWriterConfigForm;
use Laminas\Form\Form;

/**
 * TSV Writer - thin wrapper around Tsv Formatter.
 *
 * @see \BulkExport\Formatter\Tsv for the actual TSV formatting logic
 */
class TsvWriter extends CsvWriter
{
    protected $label = 'TSV (tab-separated values)'; // @translate
    protected $extension = 'tsv';
    protected $mediaType = 'text/tab-separated-values';
    protected $configFormClass = SpreadsheetWriterConfigForm::class;
    protected $paramsFormClass = SpreadsheetWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'tsv';

    protected $configKeys = [
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

    public function handleParamsForm(Form $form)
    {
        parent::handleParamsForm($form);
        $params = $this->getParams();
        // Force TSV-specific settings.
        $params['delimiter'] = "\t";
        // Unlike import, chr(0) cannot be used, because it's output.
        // Anyway, enclosure and escape are used only when there is a tabulation
        // inside the value, but this is forbidden by the format and normally
        // never exist.
        // TODO Check if the value contains a tabulation before export.
        // TODO Do not use an enclosure for tsv export.
        $params['enclosure'] = self::DEFAULT_ENCLOSURE;
        $params['escape'] = self::DEFAULT_ESCAPE;
        $this->setParams($params);
        return $this;
    }

    protected function getFormatterOptions(): array
    {
        $options = parent::getFormatterOptions();
        // Override with TSV-specific options.
        $options['delimiter'] = "\t";
        return $options;
    }
}
