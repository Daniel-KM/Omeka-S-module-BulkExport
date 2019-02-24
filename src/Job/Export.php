<?php
namespace BulkExport\Job;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Interfaces\Writer;
use BulkExport\Writer\Manager as WriterManager;
use Log\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;
use Zend\Log\Logger;

class Export extends AbstractJob
{
    /**
     * @var ExportRepresentation
     */
    protected $export;

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
        $logger = $this->getLogger();
        $export = $this->getExport();
        $this->api()->update('bulk_exports', $export->id(), ['o:job' => $this->job], [], ['isPartial' => true]);
        $writer = $this->getWriter();

        if (!$writer->isValid()) {
            throw new \Omeka\Job\Exception\RuntimeException(new PsrMessage(
                'Export error: {error}', // @translate
                ['error' => $writer->getLastErrorMessage()]
            ));
        }

        $writer->setLogger($logger);
        $writer->setJob($this);

        $logger->log(Logger::NOTICE, 'Export started'); // @translate

        // Save the label of the exporter, if needed (to create filename, etc.).
        if ($writer instanceof Parametrizable) {
            $params = $writer->getParams();
            $params['exporter_label'] = $export->exporter()->label();
            $params['export_started'] = $export->started();
            $writer->setParams($params);
        }

        $writer->process();

        $this->saveFilename($export, $writer);

        $logger->notice('Export completed'); // @translate
    }

    /**
     * Save the filename from the writer, if any.
     *
     * @param ExportRepresentation $export
     * @param Writer $writer
     */
    protected function saveFilename(ExportRepresentation $export, Writer $writer)
    {
        if (!($writer instanceof Parametrizable)) {
            return;
        }

        $params = $writer->getParams();
        if (empty($params['filename'])) {
            return;
        }

        $data = [
            'o-module-bulk-export:filename' => $params['filename'],
        ];
        $this->api()->update('bulk_exports', $export->id(), $data, [], ['isPartial' => true]);

        $this->getLogger()->notice(
            'The export is available as "{filename}".', // @translate
            ['filename' => $params['filename']]
        );
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
        $referenceId = new \Zend\Log\Processor\ReferenceId();
        $referenceId->setReferenceId('bulk/export/' . $this->getExport()->id());
        $this->logger->addProcessor($referenceId);
        return $this->logger;
    }

    /**
     * @return \Omeka\Api\Manager
     */
    protected function api()
    {
        if (!$this->api) {
            $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        }
        return $this->api;
    }

    /**
     * @return \BulkExport\Api\Representation\ExportRepresentation|null
     */
    protected function getExport()
    {
        if ($this->export) {
            return $this->export;
        }

        $id = $this->getArg('export_id');
        if ($id) {
            $content = $this->api()->search('bulk_exports', ['id' => $id, 'limit' => 1])->getContent();
            $this->export = is_array($content) && count($content) ? reset($content) : null;
        }

        if (empty($this->export)) {
            // TODO Avoid the useless trace in the log for jobs.
            throw new \Omeka\Job\Exception\InvalidArgumentException('Export record does not exist'); // @translate
        }

        return $this->export;
    }

    /**
     * @throws \Omeka\Job\Exception\InvalidArgumentException
     * @return \BulkExport\Interfaces\Writer
     */
    protected function getWriter()
    {
        $services = $this->getServiceLocator();
        $export = $this->getExport();
        $exporter = $export->exporter();
        $writerClass = $exporter->writerClass();
        $writerManager = $services->get(WriterManager::class);
        if (!$writerManager->has($writerClass)) {
            throw new \Omeka\Job\Exception\InvalidArgumentException(new PsrMessage(
                'Writer "{writer}" is not available.', // @translate
                ['writer' => $writerClass]
            ));
        }
        $writer = $writerManager->get($writerClass);
        $writer->setServiceLocator($services);
        if ($writer instanceof Configurable && $writer instanceof Parametrizable) {
            $writer->setConfig($exporter->writerConfig());
            $writer->setParams($export->writerParams());
        }
        return $writer;
    }
}
