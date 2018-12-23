<?php
namespace BulkImport\Job;

use BulkImport\Log\Logger;
use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Processor\Manager as ProcessorManager;
use BulkImport\Reader\Manager as ReaderManager;
use Omeka\Job\AbstractJob;

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

        $import = $this->getImport();
        if (!$import) {
            $this->getLogger()->log(Logger::ERR, 'Import record does not exist'); // @translate
            return;
        }

        $processor = $this->getProcessor();
        $processor->setReader($this->getReader());
        $processor->setLogger($this->getLogger());

        try {
            $this->getLogger()->log(Logger::NOTICE, 'Import started'); // @translate
            $data = ['status' => 'in progress', 'started' => new \DateTime()];
            $this->getApi()->update('bulk_imports', $import->getId(), $data, [], ['isPartial' => true]);

            $processor->process();

            $this->getLogger()->log(Logger::NOTICE, 'Import completed'); // @translate
            $data = ['status' => 'completed', 'ended' => new \DateTime()];
            $this->getApi()->update('bulk_imports', $import->getId(), $data, [], ['isPartial' => true]);
        } catch (\Exception $e) {
            $this->getLogger()->log(Logger::ERR, $e->__toString());
            $data = ['status' => 'error'];
            $this->getApi()->update('bulk_imports', $import->getId(), $data, [], ['isPartial' => true]);
        }
    }

    protected function getLogger()
    {
        if ($this->logger) {
            return $this->logger;
        }
        $this->logger = $this->getServiceLocator()->get(Logger::class);
        $this->logger->setImport($this->getImport()->getResource());
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
        $this->import = is_array($content) && count($content) ? $content[0] : null;

        return $this->import;
    }

    public function getReader()
    {
        $readerManager = $this->getServiceLocator()->get(ReaderManager::class);
        $reader = $readerManager
            ->getPlugin($this->getImport()->getImporter()->getReaderName());
        if ($reader instanceof Configurable && $reader instanceof Parametrizable) {
            $reader->setConfig($this->getImport()->getImporter()->getReaderConfig());
            $reader->setParams($this->getImport()->getReaderParams());
        }
        return $reader;
    }

    public function getProcessor()
    {
        $processorManager = $this->getServiceLocator()->get(ProcessorManager::class);
        $processor = $processorManager
            ->getPlugin($this->getImport()->getImporter()->getProcessorName());
        if ($processor instanceof Configurable && $processor instanceof Parametrizable) {
            $processor->setConfig($this->getImport()->getImporter()->getProcessorConfig());
            $processor->setParams($this->getImport()->getProcessorParams());
        }
        return $processor;
    }
}
