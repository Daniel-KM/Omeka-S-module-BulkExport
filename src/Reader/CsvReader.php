<?php
namespace BulkImport\Reader;

use BulkImport\Entry\CsvRow;
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

class CsvReader implements Reader, Configurable, Parametrizable
{
    use ConfigurableTrait, ParametrizableTrait, ServiceLocatorAwareTrait;

    protected $fh;

    protected $currentRow;

    protected $currentRowData;

    protected $headers;

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
        return 'CSV'; // @translate
    }

    public function current()
    {
        $entry = new CsvRow($this->headers, $this->currentRowData);

        return $entry;
    }

    public function key()
    {
        return $this->currentRow;
    }

    public function next()
    {
        $this->currentRowData = $this->getRow($this->fh);
        $this->currentRow++;
    }

    public function rewind()
    {
        if (!isset($this->fh)) {
            $this->fh = fopen($this->getParam('filename'), 'r');
        } else {
            fseek($this->fh, 0);
        }

        $this->headers = $this->getRow($this->fh);
        $this->currentRowData = $this->getRow($this->fh);
        $this->currentRow = 0;
    }

    public function valid()
    {
        return is_array($this->currentRowData);
    }

    public function getAvailableFields()
    {
        $fields = [];

        $filename = $this->getParam('filename');
        if ($filename && file_exists($filename)) {
            $fh = fopen($filename, 'r');
            if (false !== $fh) {
                $fields = $this->getRow($fh);
                fclose($fh);
            }
        }

        return $fields;
    }

    public function getConfigFormClass()
    {
        return CsvReaderConfigForm::class;
    }

    public function handleConfigForm(Form $form)
    {
        $values = $form->getData();

        $config = [
            'delimiter' => $values['delimiter'],
            'enclosure' => $values['enclosure'],
            'escape' => $values['escape'],
        ];

        $this->setConfig($config);
    }

    public function getParamsFormClass()
    {
        return CsvReaderParamsForm::class;
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
        ]);
    }

    protected function getRow($fh)
    {
        $delimiter = $this->getParam('delimiter', ',');
        $enclosure = $this->getParam('enclosure', '"');
        $escape = $this->getParam('escape', '\\');

        $fields = fgetcsv($fh, 0, $delimiter, $enclosure, $escape);
        if (is_array($fields)) {
            return array_map('trim', $fields);
        }
    }
}
