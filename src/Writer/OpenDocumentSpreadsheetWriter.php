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
