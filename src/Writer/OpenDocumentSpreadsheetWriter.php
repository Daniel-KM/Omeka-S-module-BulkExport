<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\SpreadsheetWriterConfigForm;
use Common\Stdlib\PsrMessage;

/**
 * ODS Writer - thin wrapper around Ods Formatter.
 *
 * @see \BulkExport\Formatter\Ods for the actual ODS formatting logic
 */
class OpenDocumentSpreadsheetWriter extends AbstractFormatterWriter
{
    protected $label = 'OpenDocument Spreadsheet'; // @translate
    protected $extension = 'ods';
    protected $mediaType = 'application/vnd.oasis.opendocument.spreadsheet';
    protected $configFormClass = SpreadsheetWriterConfigForm::class;
    protected $paramsFormClass = SpreadsheetWriterConfigForm::class;

    /**
     * The formatter to delegate to.
     */
    protected $formatterName = 'ods';

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

    public function isValid(): bool
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->lastErrorMessage = new PsrMessage(
                'To process export of "{label}", the php extensions "zip" and "xml" are required.', // @translate
                ['label' => $this->getLabel()]
            );
            return false;
        }

        $config = $this->getServiceLocator()->get('Config');
        $tempDir = $config['temp_dir'] ?: sys_get_temp_dir();
        $tempDir = $this->checkDestinationDir($tempDir);
        if (!$tempDir) {
            $this->lastErrorMessage = new PsrMessage(
                'The temporary folder "{folder}" does not exist or is not writeable.', // @translate
                ['folder' => $tempDir]
            );
            return false;
        }

        return parent::isValid();
    }
}
