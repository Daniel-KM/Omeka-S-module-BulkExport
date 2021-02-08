<?php declare(strict_types=1);

namespace BulkExport\Writer;

use Box\Spout\Common\Type;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use BulkExport\Form\Writer\SpreadsheetWriterConfigForm;
use Log\Stdlib\PsrMessage;

class OpenDocumentSpreadsheetWriter extends AbstractSpreadsheetWriter
{
    protected $label = 'OpenDocument Spreadsheet'; // @translate
    protected $extension = 'ods';
    protected $mediaType = 'application/vnd.oasis.opendocument.spreadsheet';
    protected $configFormClass = SpreadsheetWriterConfigForm::class;
    protected $paramsFormClass = SpreadsheetWriterConfigForm::class;

    protected $configKeys = [
        'separator',
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        'metadata_exclude',
    ];

    protected $paramsKeys = [
        'separator',
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'query',
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
        // The version of Box/Spout should be >= 3.0, but there is no version
        // inside php, so check against a class.
        // This check is needed, because CSV Import still uses version 2.7.
        // TODO Re-enable the check when patch https://github.com/omeka-s-modules/CSVImport/pull/182 will be included.
        /*
        if (class_exists(\Box\Spout\Reader\ReaderFactory::class)) {
            $this->lastErrorMessage = 'The dependency Box/Spout version should be >= 3.0. See readme.'; // @translate
            return false;
        }
        */
        return parent::isValid();
    }

    protected function initializeOutput()
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
