<?php
namespace BulkImport\Reader;

use Box\Spout\Common\Type;
use BulkImport\Form\SpreadsheetReaderConfigForm;
use BulkImport\Form\SpreadsheetReaderParamsForm;
use LimitIterator;
use Log\Stdlib\PsrMessage;
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
        $this->isValid($filepath);

        $this->iterator = new SplFileObject($filepath);
        $this->initializeSpreadsheetReader();

        $this->finalizePrepareIterator();
        $this->prepareHeaders();
        return $this->iterator;
    }

    protected function isValid($filepath)
    {
        parent::isValid($filepath);
        if (!$this->isUtf8($filepath)) {
            throw new \Omeka\Service\Exception\InvalidArgumentException(
                new PsrMessage(
                    'File "{filepath}" is not fully utf-8.', // @translate
                    ['filepath' => $filepath]
                )
            );
        }
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

    /**
     * Check if the file is utf-8 formatted.
     *
     * @param string $filepath
     * @return bool
     */
    protected function isUtf8($filepath)
    {
        // TODO Use another check when mb is not installed.
        if (!function_exists('mb_detect_encoding')) {
            return true;
        }

        // Check all the file, because the headers are generally ascii.
        // Nevertheless, check the lines one by one as text to avoid a memory
        // overflow with a big csv file.
        $iterator = new SplFileObject($filepath);
        $iterator->setFlags(0);
        $iterator->setCsvControl($this->getParam('delimiter', ','), $this->getParam('enclosure', '"'), $this->getParam('escape', '\\'));
        $iterator->rewind();
        foreach (new LimitIterator($iterator) as $line) {
            if (mb_detect_encoding($line, 'UTF-8', true) !== 'UTF-8') {
                return false;
            }
        }
        return true;
    }
}
