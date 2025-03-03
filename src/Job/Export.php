<?php declare(strict_types=1);

namespace BulkExport\Job;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Writer\Manager as WriterManager;
use BulkExport\Writer\WriterInterface;
use Laminas\Log\Logger;
use Common\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;

class Export extends AbstractJob
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \BulkExport\Api\Representation\ExportRepresentation
     */
    protected $export;

    /**
     * @var \BulkExport\Api\Representation\ExporterRepresentation
     */
    protected $exporter;

    /**
     * @var \BulkExport\Writer\WriterInterface
     */
    protected $writer;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->logger = $services->get('Omeka\Logger');

        $bulkExportId = $this->getArg('bulk_export_id');
        if (!$bulkExportId) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'Export record id is not set.', // @translate
            );
            return;
        }

        $this->export = $this->api->search('bulk_exports', ['id' => $bulkExportId, 'limit' => 1])->getContent();
        if (!count($this->export)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'Export record id #{id} does not exist.', // @translate
                ['id' => $bulkExportId]
            );
            return;
        }

        $this->export = reset($this->export);
        $this->exporter = $this->export->exporter();

        $processAsTask = $this->exporter->configOption('exporter', 'as_task');
        if ($processAsTask) {
            /** @var \Doctrine\ORM\EntityManager $entityManager */
            $entityManager = $services->get('Omeka\EntityManager');
            // jsonSerialize() keeps sub keys as unserialized objects.
            $newExport = $this->export->jsonSerialize();
            $newExport = array_diff_key($newExport, array_flip(['@id', 'o:id', 'o:job']));
            $newExport['o-bulk:exporter'] = $entityManager->getReference(\BulkExport\Entity\Exporter::class, $this->exporter->id());
            $newExport['o:owner'] = $services->get('Omeka\AuthenticationService')->getIdentity();
            $this->export = $this->api->create('bulk_exports', $newExport)->getContent();
        }

        $referenceId = new \Laminas\Log\Processor\ReferenceId();
        $referenceId->setReferenceId('bulk/export/' . $this->export->id());
        $this->logger->addProcessor($referenceId);

        if ($processAsTask) {
            $this->logger->notice(
                'Export as task based on export #{export_id}.', // @translate
                ['export_id' => $bulkExportId]
            );
        }

        // Make compatible with EasyAdmin tasks, that may use a fake job.
        if ($this->job->getId()) {
            $this->api->update('bulk_exports', $this->export->id(), ['o:job' => $this->job], [], ['isPartial' => true]);
        }

        $this->writer = $this->getWriter();
        if (!$this->writer) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'Writer "{writer}" is not available.', // @translate
                ['writer' => $this->exporter->writerClass()]
            );
            return;
        }

        // Save the label of the exporter, if needed (to create filename, etc.).
        // Should be prepared befoer checking validity.
        if ($this->writer instanceof Parametrizable) {
            $params = $this->writer->getParams();
            $params['export_id'] = $this->export->id();
            $params['exporter_label'] = $this->exporter->label();
            $params['export_started'] = $this->export->started();
            $this->writer->setParams($params);
            $siteSlug = $this->writer->getParam('site_slug');
        } else {
            $siteSlug = null;
        }

        if (!$this->writer->isValid()) {
            throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                'Export error: {error}', // @translate
                ['error' => $this->writer->getLastErrorMessage()]
            ));
        }

        $this->writer
            ->setLogger($this->logger)
            ->setJob($this);

        $this->logger->log(Logger::NOTICE, 'Export started'); // @translate

        // TODO Remove checking routes in Omeka v3.1.
        // Avoid a fatal error with background job when there is no route.
        $this->prepareRouteMatchAndSiteSettings($siteSlug);
        $this->prepareServerUrl();

        $this->writer->process();

        $this->saveFilename();

        $this->logger->notice('Export completed'); // @translate

        $notify = (bool) $this->exporter->configOption('exporter', 'notify_end');
        if ($notify) {
            $this->notifyJobEnd();
        }
    }

    /**
     * Save the filename from the writer, if any.
     *
     * @param ExportRepresentation $export
     * @param WriterInterface $writer
     */
    protected function saveFilename(): self
    {
        if (!($this->writer instanceof Parametrizable)) {
            return $this;
        }

        $params = $this->writer->getParams();
        if (empty($params['filename'])) {
            return $this;
        }

        $data = [
            'o:filename' => $params['filename'],
        ];
        $this->export = $this->api->update('bulk_exports', $this->export->id(), $data, [], ['isPartial' => true])->getContent();
        $filename = $this->export->filename(true);
        if (!$filename) {
            return $this;
        }

        $fileUrl = $this->export->fileUrl();
        $filesize = $this->export->filesize();
        if (!$fileUrl) {
            $this->logger->notice(
                'The export is available locally as specified (size: {size} bytes).', // @translate
                ['size' => $filesize]
            );
            return $this;
        }

        $this->logger->notice(
            'The export is available at {link} (size: {size} bytes).', // @translate
            [
                'link' => sprintf('<a href="%1$s" download="%2$s" target="_self">%2$s</a>', $fileUrl, basename($filename)),
                'size' => $filesize,
            ]
        );

        return $this;
    }

    protected function getWriter(): ?\BulkExport\Writer\WriterInterface
    {
        $services = $this->getServiceLocator();
        $writerClass = $this->exporter->writerClass();
        $writerManager = $services->get(WriterManager::class);
        if (!$writerManager->has($writerClass)) {
            return null;
        }
        $writer = $writerManager->get($writerClass);
        $writer->setServiceLocator($services);
        if ($writer instanceof Configurable) {
            $writer->setConfig($this->exporter->writerConfig());
        }
        if ($writer instanceof Parametrizable) {
            $writer->setParams($this->export->writerParams());
        }
        return $writer;
    }

    /**
     * Set the default route and the default slug.
     *
     *  This method avoids crash when output of value is html and that the site
     *  slug is needed for resource->siteUrl() used in linked resources.
     *
     * @param string $siteSlug
     */
    protected function prepareRouteMatchAndSiteSettings($siteSlug)
    {
        $services = $this->getServiceLocator();

        if ($siteSlug) {
            try {
                $site = $this->api->read('sites', ['slug' => $siteSlug])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
            }
        } else {
            $defaultSiteId = $services->get('Omeka\Settings')->get('default_site');
            try {
                $site = $this->api->read('sites', ['id' => $defaultSiteId])->getContent();
                $siteSlug = $site->slug();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
            }
        }

        if (empty($site)) {
            $site = $this->api->search('sites', ['limit' => 1])->getContent();
            $site = $site ? reset($site) : null;
            $siteSlug = $site ? $site->slug() : '***';
        }

        /** @var \Laminas\Mvc\MvcEvent $mvcEvent */
        $mvcEvent = $services->get('Application')->getMvcEvent();
        $routeMatch = $mvcEvent->getRouteMatch();
        if ($routeMatch) {
            $routeMatch->setParam('site-slug', $siteSlug);
        } else {
            $params = [
                '__NAMESPACE__' => 'Omeka\Controller\Site',
                '__SITE__' => true,
                'controller' => 'Index',
                'action' => 'index',
                'site-slug' => $siteSlug,
            ];
            $routeMatch = new \Laminas\Router\Http\RouteMatch($params);
            $routeMatch->setMatchedRouteName('site');
            $mvcEvent->setRouteMatch($routeMatch);
        }

        /** @var \Omeka\Settings\SiteSettings $siteSettings */
        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site ? $site->id() : 1);

        return $this;
    }

    protected function prepareServerUrl()
    {
        /** @var \Laminas\View\Helper\ServerUrl $serverUrl */
        $serverUrl = $this->getServiceLocator()->get('ViewHelperManager')->get('ServerUrl');
        if (!$serverUrl->getHost()) {
            $serverUrl->setHost('http://localhost');
        }
        return $this;
    }

    protected function notifyJobEnd(): self
    {
        $owner = $this->job->getOwner();
        if (!$owner) {
            $this->logger->log(Logger::ERR, 'No owner to notify end of process.'); // @translate
            return $this;
        }

        /**
         * @var \Omeka\Stdlib\Mailer $mailer
         */
        $services = $this->getServiceLocator();
        $mailer = $services->get('Omeka\Mailer');
        // To avoid issue with background job, use the view helper.
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $to = $owner->getEmail();
        $jobId = (int) $this->job->getId();
        $subject = new PsrMessage(
            '[Omeka Bulk Export] #{job_id}', // @translate
            ['job_id' => $jobId]
        );
        $body = new PsrMessage(
            'Export ended (job {link_open_job}#{jobId}{link_close}, {link_open_log}logs{link_close}).', // @translate
            [
                'link_open_job' => sprintf(
                    '<a href="%s">',
                    htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'id' => $jobId], ['force_canonical' => true]))
                ),
                'jobId' => $jobId,
                'link_close' => '</a>',
                'link_open_log' => sprintf(
                    '<a href="%s">',
                    htmlspecialchars($urlHelper('admin/bulk-export/id', ['controller' => 'export', 'action' => 'logs', 'id' => $this->export->id()], ['force_canonical' => true]))
                ),
            ]
        );
        $body->setEscapeHtml(false);

        $message = $mailer->createMessage();
        $message
            ->setSubject($subject)
            ->setBody((string) $body)
            ->addTo($to);

        try {
            $mailer->send($message);
        } catch (\Exception $e) {
            $this->logger->log(Logger::ERR, new \Omeka\Stdlib\Message(
                'Error when sending email to notify end of process.' // @translate
            ));
        }

        return $this;
    }
}
