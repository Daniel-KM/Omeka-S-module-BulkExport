<?php
namespace BulkExport\Reader;

use Box\Spout\Common\Type;
use BulkExport\Form\Reader\CsvReaderConfigForm;
use BulkExport\Form\Reader\CsvReaderParamsForm;
use Log\Stdlib\PsrMessage;
use SplFileObject;

/**
 * Box Spout Spreadshet reader doesn't support escape for csv (even if it
 * manages end of line and encoding). So the basic file handler is used for csv.
 * The format tsv uses the Spout reader, because there is no escape.
 */
class CsvReader extends AbstractSpreadsheetReader
{
    const DEFAULT_DELIMITER = ',';
    const DEFAULT_ENCLOSURE = '"';
    const DEFAULT_ESCAPE = '\\';

    protected $label = 'CSV'; // @translate
    protected $mediaType = 'text/csv';
    protected $configFormClass = CsvReaderConfigForm::class;
    protected $paramsFormClass = CsvReaderParamsForm::class;

    protected $configKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
    ];

    protected $paramsKeys = [
        'filename',
        'delimiter',
        'enclosure',
        'escape',
        'separator',
    ];

    /**
     * @var \SplFileObject.
     */
    protected $iterator;

    /**
     * Type of spreadsheet.
     *
     * @var string
     */
    protected $spreadsheetType = Type::CSV;

    /**
     * @var string
     */
    protected $delimiter = self::DEFAULT_DELIMITER;

    /**
     * @var string
     */
    protected $enclosure = self::DEFAULT_ENCLOSURE;

    /**
     * @var string
     */
    protected $escape = self::DEFAULT_ESCAPE;

    public function key()
    {
        // The first row is the headers, not the data.
        return parent::key() - 1;
    }

    /**
     * Reader use a foreach loop to get data. So the first output should not be
     * the available fields, but the data (numbered as 0-based).
     *
     * {@inheritDoc}
     * @see \Iterator::rewind()
     */
    public function rewind()
    {
        $this->isReady();
        $this->iterator->rewind();
        $this->next();
    }

    protected function reset()
    {
        parent::reset();
        $this->delimiter = self::DEFAULT_DELIMITER;
        $this->enclosure = self::DEFAULT_ENCLOSURE;
        $this->escape = self::DEFAULT_ESCAPE;
    }

    protected function prepareIterator()
    {
        parent::prepareIterator();
        $this->next();
    }

    protected function initializeReader()
    {
        $filepath = $this->getParam('filename');
        $this->iterator = new SplFileObject($filepath);

        $this->delimiter = $this->getParam('delimiter', self::DEFAULT_DELIMITER);
        $this->enclosure = $this->getParam('enclosure', self::DEFAULT_ENCLOSURE);
        $this->escape = $this->getParam('escape', self::DEFAULT_ESCAPE);
        $this->iterator->setFlags(
            SplFileObject::READ_CSV
            | SplFileObject::READ_AHEAD
            | SplFileObject::SKIP_EMPTY
            | SplFileObject::DROP_NEW_LINE
        );
        $this->iterator->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
    }

    protected function finalizePrepareIterator()
    {
        $this->totalEntries = iterator_count($this->iterator) - 1;
    }

    protected function prepareAvailableFields()
    {
        $this->iterator->rewind();
        $fields = $this->iterator->current();
        if (!is_array($fields)) {
            $this->lastErrorMessage = 'File has no available fields.'; // @translate
            throw new \Omeka\Service\Exception\RuntimeException($this->getLastErrorMessage());
        }
        // The data should be cleaned, since it's not an entry.
        $this->availableFields = $this->cleanData($fields);
    }

    protected function isValidFilepath($filepath, $file)
    {
        if (!parent::isValidFilepath($filepath, $file)) {
            return false;
        }
        if (!$this->isUtf8($filepath)) {
            $this->lastErrorMessage = new PsrMessage(
                'File "{filepath}" is not fully utf-8.', // @translate
                ['filename' => $file['name']]
            );
            return false;
        }
        return true;
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
        foreach ($iterator as $line) {
            if (mb_detect_encoding($line, 'UTF-8', true) !== 'UTF-8') {
                return false;
            }
        }
        return true;
    }
}
