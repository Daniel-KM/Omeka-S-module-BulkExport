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
        $fields = [];
        $filepath = $this->getParam('filename');
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
}
