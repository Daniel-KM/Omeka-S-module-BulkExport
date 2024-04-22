<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\SpreadsheetWriterConfigForm;
use Common\Stdlib\PsrMessage;
use OpenSpout\Common\Type;
use OpenSpout\Writer\Common\Creator\WriterEntityFactory;

class OpenDocumentSpreadsheetWriter extends AbstractSpreadsheetWriter
{
    protected $label = 'OpenDocument Spreadsheet'; // @translate
    protected $extension = 'ods';
    protected $mediaType = 'application/vnd.oasis.opendocument.spreadsheet';
    protected $configFormClass = SpreadsheetWriterConfigForm::class;
    protected $paramsFormClass = SpreadsheetWriterConfigForm::class;

    protected $configKeys = [
        'separator',
        'dirpath',
        'filebase',
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'query',
        'incremental',
        'include_deleted',
    ];

    protected $paramsKeys = [
        'separator',
        'dirpath',
        'filebase',
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'query',
        'incremental',
        'include_deleted',
    ];

    protected $spreadsheetType = Type::ODS;

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

    protected function initializeOutput(): self
    {
        $config = $this->getServiceLocator()->get('Config');
        $tempDir = $config['temp_dir'] ?: sys_get_temp_dir();
        $this->spreadsheetWriter = WriterEntityFactory::createODSWriter();
        $this->spreadsheetWriter
            ->setTempFolder($tempDir)
            ->openToFile($this->filepath);
        return $this;
    }
}
