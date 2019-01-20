<?php
namespace BulkExport\Writer;

use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterInterface;
use BulkExport\Form\Writer\OpenDocumentSpreadsheetWriterParamsForm;
use BulkExport\Form\Writer\SpreadsheetWriterConfigForm;
use Log\Stdlib\PsrMessage;

class OpenDocumentSpreadsheetWriter extends AbstractSpreadsheetWriter
{
    protected $label = 'OpenDocument Spreadsheet'; // @translate
    protected $extension = 'ods';
    protected $mediaType = 'application/vnd.oasis.opendocument.spreadsheet';
    protected $configFormClass = SpreadsheetWriterConfigForm::class;
    protected $paramsFormClass = OpenDocumentSpreadsheetWriterParamsForm::class;

    protected $configKeys = [
        'separator',
        'metadata',
    ];

    protected $paramsKeys = [
        'separator',
        'metadata',
    ];

    protected $spreadsheetType = Type::ODS;

    public function isValid()
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

    protected function initializeWriter(WriterInterface $writer)
    {
        $config = $this->getServiceLocator()->get('Config');
        $tempDir = $config['temp_dir'] ?: sys_get_temp_dir();
        $writer
            ->setTempFolder($tempDir);
    }
}
