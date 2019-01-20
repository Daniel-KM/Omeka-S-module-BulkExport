<?php
namespace BulkExport\Writer;

use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\WriterInterface;
use BulkExport\Form\Writer\OpenDocumentSpreadsheetWriterParamsForm;
use BulkExport\Form\Writer\SpreadsheetWriterConfigForm;
use Log\Stdlib\PsrMessage;

class OpenDocumentSpreadsheetWriter extends AbstractSpreadsheetWriter
{
    protected $label = 'OpenDocument Spreadsheet'; // @translate
    protected $mediaType = 'application/vnd.oasis.opendocument.spreadsheet';
    protected $configFormClass = SpreadsheetWriterConfigForm::class;
    protected $paramsFormClass = OpenDocumentSpreadsheetWriterParamsForm::class;

    protected $configKeys = [
        'separator',
    ];

    protected $paramsKeys = [
        'filename',
        'separator',
    ];

    /**
     * @var \Box\Spout\Writer\ODS\Writer
     */
    protected $iterator;

    /**
     * Type of spreadsheet.
     *
     * @var string
     */
    protected $spreadsheetType = Type::ODS;

    /**
     * @var WriterInterface
     */
    protected $spreadsheetWriter;

    public function isValid()
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->errorMessage = new PsrMessage(
                'To process export of "{label}", the php extensions "zip" and "xml" are required.', // @translate
                ['label' => $this->getLabel()]
            );
            return false;
        }
        return parent::isValid();
    }

    public function key()
    {
        // The first row is the headers, not the data, and it's numbered from 1,
        // not 0.
        return parent::key() - 2;
    }

    /**
     * Spout Writer doesn't support rewind for xml (doesSupportStreamWrapper()),
     * so the iterator should be reinitialized.
     *
     * Writer use a foreach loop to get data. So the first output should not be
     * the available fields, but the data (numbered as 0-based).
     *
     * {@inheritDoc}
     * @see \BulkExport\Writer\AbstractWriter::rewind()
     */
    public function rewind()
    {
        $this->isReady;
        $this->initializeWriter();
        $this->next();
    }

    protected function reset()
    {
        parent::reset();
        if ($this->spreadsheetWriter) {
            $this->spreadsheetWriter->close();
        }
    }

    protected function prepareIterator()
    {
        parent::prepareIterator();
        $this->next();
    }

    protected function initializeWriter()
    {
        if ($this->spreadsheetWriter) {
            $this->spreadsheetWriter->close();
        }

        $this->spreadsheetWriter = WriterFactory::create($this->spreadsheetType);

        $filepath = $this->getParam('filename');
        try {
            $this->spreadsheetWriter->open($filepath);
        } catch (\Box\Spout\Common\Exception\IOException $e) {
            throw new \Omeka\Service\Exception\RuntimeException(
                new PsrMessage(
                    'File "{filepath}" cannot be open.', // @translate
                    ['filepath' => $filepath]
                )
            );
        }

        $this->spreadsheetWriter
            // ->setTempFolder($this->config['temp_dir'])
            ->setShouldFormatDates(false);

        // Process first sheet only.
        $this->iterator = null;
        foreach ($this->spreadsheetWriter->getSheetIterator() as $sheet) {
            $this->iterator = $sheet->getRowIterator();
            break;
        }
    }

    protected function finalizePrepareIterator()
    {
        $this->totalEntries = iterator_count($this->iterator) - 1;
        $this->initializeWriter();
    }

    protected function prepareAvailableFields()
    {
        foreach ($this->iterator as $fields) {
            break;
        }
        if (!is_array($fields)) {
            $this->lastErrorMessage = 'File has no available fields.'; // @translate
            throw new \Omeka\Service\Exception\RuntimeException($this->getLastErrorMessage());
        }
        // The data should be cleaned, since it's not an entry.
        $this->availableFields = $this->cleanData($fields);
        $this->initializeWriter();
    }
}
