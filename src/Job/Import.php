<?php
namespace BulkImport\Job;

use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Processor\Manager as ProcessorManager;
use BulkImport\Reader\Manager as ReaderManager;
use Log\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;
use Zend\Log\Logger;

class Import extends AbstractJob
{
    protected $import;

    /**
     * @var \Zend\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    public function perform()
    {
        ini_set('auto_detect_line_endings', true);

        $logger = $this->getLogger();

        $import = $this->getImport();
        if (!$import) {
            $logger->log(Logger::ERR, 'Import record does not exist'); // @translate
            return;
        }

        $reader = $this->getReader();
        if (empty($reader)) {
            $this->log(Logger::ERR, \BulkImport\Entity\Import::STATUS_ERROR, new PsrMessage('Reader "{reader}" is not available.', ['reader' => $import->getImporter()->getReaderName()])); // @translate
            return;
        }

        $processor = $this->getProcessor();
        if (empty($processor)) {
            $this->log(Logger::ERR, \BulkImport\Entity\Import::STATUS_ERROR, new PsrMessage('Processor "{processor}" is not available.', ['processor' => $import->getImporter()->getProcessorName()])); // @translate
            return;
        }

        $processor->setReader($reader);
        $processor->setLogger($logger);

        try {
            $logger->log(Logger::NOTICE, 'Import started'); // @translate
            $data = ['status' => \BulkImport\Entity\Import::STATUS_IN_PROGRESS, 'started' => new \DateTime()];
            $this->getApi()->update('bulk_imports', $import->getId(), $data, [], ['isPartial' => true]);

            $processor->process();

            $logger->log(Logger::NOTICE, 'Import completed'); // @translate
            $data = ['status' => \BulkImport\Entity\Import::STATUS_COMPLETED, 'ended' => new \DateTime()];
            $this->getApi()->update('bulk_imports', $import->getId(), $data, [], ['isPartial' => true]);
        } catch (\Exception $e) {
            $this->log(Logger::ERR, \BulkImport\Entity\Import::STATUS_ERROR, 'Import error: {exception}', ['exception' => $e]);
        }
    }

    /**
     * Get the logger for the bulk process (the Omeka one, with reference id).
     *
     * @return \Zend\Log\Logger
     */
    protected function getLogger()
    {
        if ($this->logger) {
            return $this->logger;
        }
        $this->logger = $this->getServiceLocator()->get('Omeka\Logger');
        $referenceIdProcessor = new \Zend\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('bulk/import/' . $this->getImport()->getId());
        $this->logger->addProcessor($referenceIdProcessor);
        return $this->logger;
    }

    /**
     * @return \Omeka\Api\Manager
     */
    protected function getApi()
    {
        if ($this->api) {
            return $this->api;
        }

        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        return $this->api;
    }

    /**
     * @return \BulkImport\Api\Representation\ImportRepresentation|null
     */
    protected function getImport()
    {
        if ($this->import) {
            return $this->import;
        }

        $id = $this->getArg('import_id');
        if (!$id) {
            return null;
        }

        $content = $this->getApi()->search('bulk_imports', ['id' => $id, 'limit' => 1])->getContent();
        $this->import = is_array($content) && count($content) ? reset($content) : null;

        return $this->import;
    }

    public function getReader()
    {
        $readerName = $this->getImport()->getImporter()->getReaderName();
        $readerManager = $this->getServiceLocator()->get(ReaderManager::class);
        if (!$readerManager->has($readerName)) {
            return;
        }
        $reader = $readerManager->get($readerName);
        $reader->setServiceLocator($this->getServiceLocator());
        if ($reader instanceof Configurable && $reader instanceof Parametrizable) {
            $reader->setConfig($this->getImport()->getImporter()->getReaderConfig());
            $reader->setParams($this->getImport()->getReaderParams());
        }
        return $reader;
    }

    public function getProcessor()
    {
        $processorName = $this->getImport()->getImporter()->getProcessorName();
        $processorManager = $this->getServiceLocator()->get(ProcessorManager::class);
        if (!$processorManager->has($processorName)) {
            return;
        }
        $processor = $processorManager->get($processorName);
        $processor->setServiceLocator($this->getServiceLocator());
        if ($processor instanceof Configurable && $processor instanceof Parametrizable) {
            $processor->setConfig($this->getImport()->getImporter()->getProcessorConfig());
            $processor->setParams($this->getImport()->getProcessorParams());
        }
        return $processor;
    }

    protected function log($severity, $status, $message)
    {
        $this->getLogger()->log($severity, $message);
        $data = ['status' => $status];
        $this->getApi()->update('bulk_imports', $this->getImport()->getId(), $data, [], ['isPartial' => true]);
    }
}
