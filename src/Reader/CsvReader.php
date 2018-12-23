<?php
namespace BulkImport\Reader;

use Box\Spout\Common\Type;
use BulkImport\Entry\SpreadsheetRow;
use BulkImport\Form\CsvReaderConfigForm;
use BulkImport\Form\CsvReaderParamsForm;
use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Interfaces\Reader;
use BulkImport\Traits\ConfigurableTrait;
use BulkImport\Traits\ParametrizableTrait;
use BulkImport\Traits\ServiceLocatorAwareTrait;
use LimitIterator;
use Log\Stdlib\PsrMessage;
use SplFileObject;
use Zend\Form\Form;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Box Spout Spreadshet reader doesn't support escape for csv (even if it
 * manages end of line and encoding). So the basic file handler is used for csv.
 * The format tsv uses the Spout reader, because there is no escape.
 */
class CsvReader implements Reader, Configurable, Parametrizable
{
    use ConfigurableTrait, ParametrizableTrait, ServiceLocatorAwareTrait;

    protected $label = 'CSV'; // @translate
    protected $mediaType = 'text/csv';
    protected $spreadsheetType = Type::CSV;
    protected $configFormClass = CsvReaderConfigForm::class;
    protected $paramsFormClass = CsvReaderParamsForm::class;

    protected $fh;

    protected $currentRow = 0;

    protected $currentRowData = [];

    protected $headers = [];

    /**
     * CsvReader constructor.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getAvailableFields()
    {
        return $this->getHeaders();
    }

    public function getConfigFormClass()
    {
        return $this->configFormClass;
    }

    public function handleConfigForm(Form $form)
    {
        $values = $form->getData();

        $config = [
            'delimiter' => $values['delimiter'],
            'enclosure' => $values['enclosure'],
            'escape' => $values['escape'],
            'separator' => $values['separator'],
        ];

        $this->setConfig($config);
    }

    public function getParamsFormClass()
    {
        return $this->paramsFormClass;
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
            throw new \Exception('temp_dir is not configured'); // @translate
        }

        $filename = tempnam($tempDir, 'omeka');
        if (!move_uploaded_file($file['tmp_name'], $filename)) {
            throw new \Exception(sprintf('Unable to move uploaded file to %s', $filename)); // @translate
        }

        $this->setParams([
            'filename' => $filename,
            'delimiter' => $values['delimiter'],
            'enclosure' => $values['enclosure'],
            'escape' => $values['escape'],
            'separator' => $values['separator'],
        ]);
    }

    public function current()
    {
        $entry = new SpreadsheetRow($this->headers, $this->currentRowData);
        return $entry;
    }

    public function key()
    {
        return $this->currentRow;
    }

    public function next()
    {
        $this->currentRowData = $this->getRow($this->fh);
        ++$this->currentRow;
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
        if (isset($this->fh)) {
            fseek($this->fh, 0);
        } else {
            $filepath = $this->getParam('filename');
            $this->isValid($filepath);
            $this->fh = fopen($filepath, 'r');
        }

        // The headers and the first row are prepared, so the "foreach" loop
        // starts on the first row, numbered current row 0.
        $this->headers = $this->getRow($this->fh);
        $this->currentRowData = $this->getRow($this->fh);
        $this->currentRow = 0;
    }

    public function valid()
    {
        return is_array($this->currentRowData);
    }

    protected function getHeaders()
    {
        $filepath = $this->getParam('filename');
        $this->isValid($filepath);

        $fields = [];
        if ($filepath && file_exists($filepath) && is_readable($filepath)) {
            $fh = fopen($filepath, 'r');
            if (false !== $fh) {
                $fields = $this->getRow($fh);
                fclose($fh);
            }
        }
        return $fields;
    }

    protected function getRow($fh)
    {
        $delimiter = $this->getParam('delimiter', ',');
        $enclosure = $this->getParam('enclosure', '"');
        $escape = $this->getParam('escape', '\\');

        $fields = fgetcsv($fh, 0, $delimiter, $enclosure, $escape);
        if (is_array($fields)) {
            return array_map([$this, 'trimUnicode'], $fields);
        }
    }

    protected function trimUnicode($string)
    {
        return preg_replace('/^[\h\v\s[:blank:][:space:]]+|[\h\v\s[:blank:][:space:]]+$/u', '', $string);
    }

    /**
     * @param string $filepath
     * @throw \Omeka\Service\Exception\InvalidArgumentException
     */
    protected function isValid($filepath)
    {
        if (empty($filepath)) {
            throw new \Omeka\Service\Exception\InvalidArgumentException(
                new PsrMessage(
                    'File "{filepath}" doesn’t exist.', // @translate
                    ['filepath' => $filepath]
                )
            );
        }
        if (!filesize($filepath)) {
            throw new \Omeka\Service\Exception\InvalidArgumentException(
                new PsrMessage(
                    'File "{filepath}" is empty.', // @translate
                    ['filepath' => $filepath]
                )
            );
        }
        if (!is_readable($filepath)) {
            throw new \Omeka\Service\Exception\InvalidArgumentException(
                new PsrMessage(
                    'File "{filepath}" is not readable.', // @translate
                    ['filepath' => $filepath]
                )
            );
        }

        if (!$this->isUtf8($filepath)) {
            throw new \Omeka\Service\Exception\InvalidArgumentException(
                new PsrMessage(
                    'File "{filepath}" is not fully utf-8.', // @translate
                    ['filepath' => $filepath]
                )
            );
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
