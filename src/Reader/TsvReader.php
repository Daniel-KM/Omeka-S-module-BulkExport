<?php
namespace BulkImport\Reader;

use Box\Spout\Common\Type;
use BulkImport\Form\SpreadsheetReaderConfigForm;
use BulkImport\Form\SpreadsheetReaderParamsForm;
use SplFileObject;

class TsvReader extends AbstractSpreadsheetReader
{
    protected $label = 'TSV (tab-separated values)'; // @translate
    protected $mediaType = 'text/tab-separated-values';
    protected $spreadsheetType = Type::CSV;
    protected $configFormClass = SpreadsheetReaderConfigForm::class;
    protected $paramsFormClass = SpreadsheetReaderParamsForm::class;

    /**
     * Box Spout is not used for tsv, because it cannot set the escape.
     *
     * {@inheritDoc}
     * @see \BulkImport\Reader\AbstractSpreadsheetReader::prepareIterator()
     */
    protected function prepareIterator()
    {
        $this->reset();

        $filepath = $this->getParam('filename');
        $this->iterator = new SplFileObject($filepath);
        $this->initializeSpreadsheetReader();

        $this->finalizePrepareIterator();
        $this->prepareHeaders();
        return $this->iterator;
    }

    protected function initializeSpreadsheetReader()
    {
        if ($this->iterator) {
            $this->iterator->setFlags(
                SplFileObject::READ_CSV
                | SplFileObject::READ_AHEAD
                | SplFileObject::SKIP_EMPTY
                | SplFileObject::DROP_NEW_LINE
            );
            $this->iterator->setCsvControl("\t", chr(0), chr(0));
        }
    }
}
