<?php
namespace BulkImport\Reader;

/**
 * Note: Reader isn’t traversable and has no rewind, so some hacks are required.
 * Nevertheless, the library is quick and efficient and the module uses it only
 * as recommended (as stream ahead).
 */
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\ReaderInterface;
use BulkImport\Entry\SpreadsheetRow;
use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Interfaces\Reader;
use BulkImport\Traits\ConfigurableTrait;
use BulkImport\Traits\ParametrizableTrait;
use BulkImport\Traits\ServiceLocatorAwareTrait;
use Countable;
use LimitIterator;
use Log\Stdlib\PsrMessage;
use Zend\Form\Form;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractSpreadsheetReader implements Reader, Configurable, Parametrizable, Countable
{
    use ConfigurableTrait, ParametrizableTrait, ServiceLocatorAwareTrait;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $configFormClass;

    /**
     * @var string
     */
    protected $paramsFormClass;

    /**
     * The media type processed by this class.
     *
     * @var string
     */
    protected $mediaType;

    /**
     * @var string
     */
    protected $spreadsheetType;

    /**
     * @var ReaderInterface
     */
    protected $spreadsheetReader;

    /**
     * @var \Iterator
     */
    protected $iterator;

    /**
     * @var int
     */
    protected $iteratorCount;

    /**
     * @var int
     *
     * See rewind() for explanation of the current row.
     */
    protected $currentRow = 1;

    /**
     * @var array
     */
    protected $currentRowData;

    /**
     * @var array
     */
    protected $headers;

    /**
     * SpreadsheetReader constructor.
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
        $config = [];
        $config['separator'] = $values['separator'];
        $this->setConfig($config);
    }

    public function getParamsFormClass()
    {
        return $this->paramsFormClass;
    }

    public function handleParamsForm(Form $form)
    {
        // Move file to temp dir first.
        $file = $form->get('file')->getValue();
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

        $values = $form->getData();

        $params = [];
        $params['filename'] = $filename;
        $params['separator'] = $values['separator'];
        $this->setParams($params);
    }

    public function current()
    {
        $this->getIterator();
        $entry = new SpreadsheetRow($this->headers, $this->currentRowData);
        return $entry;
    }

    public function key()
    {
        $this->getIterator();
        return $this->currentRow - 1;
    }

    public function next()
    {
        $this->getIterator();
        ++$this->currentRow;
        $this->currentRowData = $this->getRow($this->currentRow);
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
        $this->prepareIterator();
    }

    public function valid()
    {
        $this->getIterator();
        return is_array($this->currentRowData);
    }

    public function count()
    {
        $this->getIterator();
        return $this->iteratorCount - 1;
    }

    protected function getHeaders()
    {
        $this->getIterator();
        return $this->headers;
    }

    protected function getRow($index)
    {
        $rows = $this->getRows($index, 1);
        return  is_array($rows)
            ? reset($rows)
            : null;
    }

    protected function getRows($index = 0, $limit = -1)
    {
        $iterator = $this->getIterator();
        if (empty($iterator)) {
            return;
        }
        if ($index >= $this->iteratorCount) {
            return;
        }

        $rows = [];
        $limitIterator = new LimitIterator($iterator, $index, $limit);
        foreach ($limitIterator as $row) {
            $rows[] = $this->cleanRow($row);
        }
        return $rows;
    }

   protected function cleanRow(array $row)
   {
       return array_map([$this, 'trimUnicode'], $row);
   }

    protected function trimUnicode($string)
    {
        return preg_replace('/^[\h\v\s[:blank:][:space:]]+|[\h\v\s[:blank:][:space:]]+$/u', '', $string);
    }

    protected function getIterator()
    {
        if ($this->iterator) {
            return $this->iterator;
        }
        return $this->prepareIterator();
    }

    /**
     * @uses \Box\Spout\Reader\ReaderFactory
     * @throws \Omeka\Service\Exception\RuntimeException
     * @return \Iterator
     */
    protected function prepareIterator()
    {
        $this->reset();

        $filepath = $this->getParam('filename');
        $this->isValid($filepath);

        $this->spreadsheetReader = ReaderFactory::create($this->spreadsheetType);
        $this->initializeSpreadsheetReader();

        try {
            $this->spreadsheetReader->open($filepath);
        } catch (\Box\Spout\Common\Exception\IOException $e) {
            throw new \Omeka\Service\Exception\InvalidArgumentException(
                new PsrMessage(
                    'File "{filepath}" cannot be open.', // @translate
                    ['filepath' => $filepath]
                )
            );
        }

        $this->spreadsheetReader
            // ->setTempFolder($this->config['temp_dir'])
            ->setShouldFormatDates(false);

        // Process first sheet only.
        foreach ($this->spreadsheetReader->getSheetIterator() as $sheet) {
            $this->iterator = $sheet->getRowIterator();
            break;
        }

        $this->finalizePrepareIterator();
        $this->prepareHeaders();
        return $this->iterator;
    }

    protected function reset()
    {
        if ($this->spreadsheetReader) {
            $this->spreadsheetReader->close();
        }
        $this->spreadsheetReader = null;
        $this->iterator = null;
        $this->iteratorCount = null;
        $this->currentRow = 1;
        $this->currentRowData = null;
        $this->headers = null;
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
    }

    /**
     * Called only by prepareIterator() before opening reader.
     */
    protected function initializeSpreadsheetReader()
    {
        // Nothing to do by default.
    }

    /**
     * Called only by prepareIterator() after opening reader.
     */
    protected function finalizePrepareIterator()
    {
        $this->iteratorCount = iterator_count($this->iterator);
    }

    /**
     * The headers and the first row are prepared, so the "foreach" loop starts
     * on the first row, numbered current row 0 (via key()).
     */
    protected function prepareHeaders()
    {
        $this->headers = null;
        foreach ($this->iterator as $headers) {
            break;
        }

        if (!is_array($headers)) {
            return;
        }
        $headers = $this->cleanRow($headers);

        // Remove last empty headers.
        $headers = array_reverse($headers, true);
        foreach ($headers as $key => $header) {
            if (strlen($header)) {
                break;
            }
            unset($headers[$key]);
        }
        $this->headers = array_reverse($headers, true);

        $this->currentRow = 0;
        $this->next();
    }
}
