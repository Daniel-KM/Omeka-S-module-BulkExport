<?php
namespace BulkImport\Reader;

use Box\Spout\Common\Type;
use BulkImport\Form\CsvReaderConfigForm;
use BulkImport\Form\CsvReaderParamsForm;
use Log\Stdlib\PsrMessage;
use SplFileObject;
use Zend\Form\Form;

/**
 * Box Spout Spreadshet reader doesn't support escape for csv (even if it
 * manages end of line and encoding). So the basic file handler is used for csv.
 * The format tsv uses the Spout reader, because there is no escape.
 */
class CsvReader extends AbstractReader
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

    public function isValid()
    {
        $this->lastErrorMessage = null;

        $filepath = $this->getParam('filename');
        if (empty($filepath)) {
            $this->lastErrorMessage = new PsrMessage(
                'File "{filepath}" doesn’t exist.', // @translate
                ['filepath' => $filepath]
            );
            return false;
        }
        if (!filesize($filepath)) {
            $this->lastErrorMessage = new PsrMessage(
                'File "{filepath}" is empty.', // @translate
                ['filepath' => $filepath]
            );
            return false;
        }
        if (!is_readable($filepath)) {
            $this->lastErrorMessage = new PsrMessage(
                'File "{filepath}" is not readable.', // @translate
                ['filepath' => $filepath]
            );
            return false;
        }
        if (!$this->isUtf8($filepath)) {
            $this->lastErrorMessage = new PsrMessage(
                'File "{filepath}" is not fully utf-8.', // @translate
                ['filepath' => $filepath]
            );
            return false;
        }
        return true;
    }

    public function handleParamsForm(Form $form)
    {
        $values = $form->getData();
        $file = $form->get('file')->getValue();

        // Move file.
        $systemConfig = $this->getServiceLocator()->get('Config');
        $tempDir = isset($systemConfig['temp_dir'])
            ? $systemConfig['temp_dir']
            : null;
        if (!$tempDir) {
            throw new \Omeka\Service\Exception\RuntimeException(
                'The "temp_dir" is not configured' // @translate
            );
        }

        $filename = tempnam($tempDir, 'omeka');
        if (!move_uploaded_file($file['tmp_name'], $filename)) {
            throw new \Omeka\Service\Exception\RuntimeException(
                new PsrMessage(
                    'Unable to move uploaded file to %s', // @translate
                    ['filename' => $filename]
                )
            );
        }

        $params = array_intersect_key($values, array_flip($this->paramsKeys));
        $params['filename'] = $filename;
        $this->setParams($params);
        $this->reset();
    }

    public function key()
    {
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
        $this->reset();
        if (!$this->isValid()) {
            throw new \Omeka\Service\Exception\RuntimeException($this->getLastErrorMessage());
        }

        $filepath = $this->getParam('filename');
        $this->iterator = new SplFileObject($filepath);
        $this->initializeReader();

        $this->finalizePrepareIterator();
        $this->prepareAvailableFields();

        $this->isReady = true;
        $this->next();
    }

    protected function initializeReader()
    {
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
        $this->availableFields = array_map([$this, 'trimUnicode'], $fields);
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
