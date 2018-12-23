<?php
namespace BulkImport\Reader;

use BulkImport\Entry\Entry;
use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Interfaces\Reader;
use BulkImport\Traits\ConfigurableTrait;
use BulkImport\Traits\ParametrizableTrait;
use BulkImport\Traits\ServiceLocatorAwareTrait;
use Iterator;
use Zend\Form\Form;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractReader implements Reader, Configurable, Parametrizable
{
    use ConfigurableTrait, ParametrizableTrait, ServiceLocatorAwareTrait;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var array
     */
    protected $availableFields = [];

    /**
     * @var string|null
     */
    protected $lastErrorMessage;

    /**
     * @var string
     */
    protected $configFormClass;

    /**
     * @var string
     */
    protected $paramsFormClass;

    /**
     * var array
     */
    protected $configKeys = [];

    /**
     * var array
     */
    protected $paramsKeys = [];

    /**
     * @var \Iterator
     */
    protected $iterator;

    /**
     * @var int
     */
    protected $totalEntries;

    /**
     * @var array
     */
    protected $currentData = [];

    /**
     * @var bool
     */
    protected $isReady;

    /**
     * Reader constructor.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $services)
    {
        $this->setServiceLocator($services);
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function isValid()
    {
        return true;
    }

    public function getLastErrorMessage()
    {
        return $this->lastErrorMessage;
    }

    public function getAvailableFields()
    {
        $this->isReady();
        return $this->availableFields;
    }

    public function getConfigFormClass()
    {
        return $this->configFormClass;
    }

    public function handleConfigForm(Form $form)
    {
        $values = $form->getData();
        $config = array_intersect_key($values, array_flip($this->configKeys));
        $this->setConfig($config);
        $this->reset();
    }

    public function getParamsFormClass()
    {
        return $this->paramsFormClass;
    }

    public function handleParamsForm(Form $form)
    {
        $values = $form->getData();
        $params = array_intersect_key($values, array_flip($this->paramsKeys));
        $this->setParams($params);
        $this->reset();
    }

    public function current()
    {
        $this->isReady();
        $this->currentData = $this->iterator->current();
        if (!is_array($this->currentData)) {
            return null;
        }
        $this->currentData = $this->cleanData($this->currentData);
        return new Entry($this->availableFields, $this->currentData);
    }

    public function key()
    {
        $this->isReady();
        return $this->iterator->key();
    }

    public function next()
    {
        $this->isReady();
        $this->iterator->next();
    }

    public function rewind()
    {
        $this->isReady();
        $this->iterator->rewind();
    }

    public function valid()
    {
        $this->isReady();
        return $this->iterator->valid();
    }

    public function count()
    {
        $this->isReady();
        return $this->totalEntries;
    }

    protected function isReady()
    {
        if ($this->isReady) {
            return true;
        }

        $this->prepareIterator();
        return $this->isReady;
    }

    protected function reset()
    {
        $this->availableFields = [];
        $this->iterator = null;
        $this->totalEntries = null;
        $this->currentData = [];
        $this->iisReady = false;
    }

    /**
     * @throws \Omeka\Service\Exception\RuntimeException
     */
    protected function prepareIterator()
    {
        $this->reset();
        if (!$this->isValid()) {
            throw new \Omeka\Service\Exception\RuntimeException($this->getLastErrorMessage());
        }

        $this->iterator = new \ArrayIterator([]);
        $this->initializeReader();

        $this->finalizePrepareIterator();
        $this->prepareAvailableFields();

        $this->isReady = true;
    }

    /**
     * Called only by prepareIterator() before opening reader.
     */
    protected function initializeReader()
    {
    }

    /**
     * Called only by prepareIterator() after opening reader.
     */
    protected function finalizePrepareIterator()
    {
        $this->totalEntries = iterator_count($this->iterator);
    }

    /**
     * The fields are an array.
     */
    protected function prepareAvailableFields()
    {
    }

    protected function cleanData(array $data)
    {
        return array_map([$this, 'trimUnicode'], $data);
    }

    /**
     * Trim all whitespace, included the unicode ones.
     *
     * @param string $string
     * @return string
     */
    protected function trimUnicode($string)
    {
        return preg_replace('/^[\h\v\s[:blank:][:space:]]+|[\h\v\s[:blank:][:space:]]+$/u', '', $string);
    }
}
