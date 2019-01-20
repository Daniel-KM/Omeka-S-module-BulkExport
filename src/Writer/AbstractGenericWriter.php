<?php
namespace BulkExport\Writer;

use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;

abstract class AbstractGenericWriter extends AbstractWriter
{
    protected $mediaTypeWriters = [];

    /**
     * @var \BulkExport\Interfaces\Writer
     */
    protected $writer;

    public function isValid()
    {
        $this->lastErrorMessage = null;
        // TODO Currently, the generic writer requires an uploaded file to get the specific writer.
        $file = $this->getParam('file');
        if (!$file) {
            return false;
        }
        if (!parent::isValid()) {
            return false;
        }
        $this->initializeWriter();
        $this->isReady = true;
        return $this->writer->isValid();
    }

    public function getLastErrorMessage()
    {
        if ($this->writer && $message = $this->writer->getLastErrorMessage()) {
            return $message;
        }
        return parent::getLastErrorMessage();
    }

    public function getAvailableFields()
    {
        $this->isReady();
        return $this->writer->getAvailableFields();
    }

    public function current()
    {
        $this->isReady();
        return $this->writer->current();
    }

    public function key()
    {
        $this->isReady();
        return $this->writer->key();
    }

    public function next()
    {
        $this->isReady();
        $this->writer->next();
    }

    public function rewind()
    {
        $this->isReady();
        $this->writer->rewind();
    }

    public function valid()
    {
        $this->isReady();
        return $this->writer->valid();
    }

    public function count()
    {
        $this->isReady();
        return $this->writer->count();
    }

    protected function isReady()
    {
        if ($this->isReady) {
            return true;
        }

        $this->prepareIterator();
        return $this->isReady;
    }

    protected function prepareIterator()
    {
        $this->reset();
        if (!$this->isValid()) {
            throw new \Omeka\Service\Exception\RuntimeException($this->getLastErrorMessage());
        }
        $this->initializeWriter();
        $this->isReady = true;
    }

    protected function initializeWriter()
    {
        $file = $this->getParam('file');
        $writerClass = $this->mediaTypeWriters[$file['type']];
        $this->writer = new $writerClass($this->getServiceLocator());
        if ($this->writer instanceof Configurable) {
            $this->writer->setConfig($this->getConfig());
        }
        if ($this->writer instanceof Parametrizable) {
            $this->writer->setParams($this->getParams());
        }
    }
}
