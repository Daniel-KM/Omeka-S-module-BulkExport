<?php declare(strict_types=1);

namespace BulkExport\Job;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Writer\Manager as WriterManager;
use BulkExport\Writer\WriterInterface;
use Laminas\Log\Logger;
use Log\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;

class Export extends AbstractJob
{
    /**
     * @var ExportRepresentation
     */
    protected $export;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    public function perform(): void
    {
        // Init logger and export.
        $this->getLogger();

        $export = $this->getExport();
        $this->api()->update('bulk_exports', $export->id(), ['o:job' => $this->job], [], ['isPartial' => true]);
        $writer = $this->getWriter();

        if (!$writer->isValid()) {
            throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                'Export error: {error}', // @translate
                ['error' => $writer->getLastErrorMessage()]
            ));
        }

        $writer
            ->setLogger($this->logger)
            ->setJob($this);

        $this->logger->log(Logger::NOTICE, 'Export started'); // @translate

        // Save the label of the exporter, if needed (to create filename, etc.).
        if ($writer instanceof Parametrizable) {
            $params = $writer->getParams();
            $params['exporter_label'] = $export->exporter()->label();
            $params['export_started'] = $export->started();
            $writer->setParams($params);
            $siteSlug = $writer->getParam('site_slug');
        } else {
            $siteSlug = null;
        }

        // TODO Remove checking routes in Omeka v3.1.
        // Avoid a fatal error with background job when there is no route.
        $this->prepareRouteMatchAndSiteSettings($siteSlug);
        $this->prepareServerUrl();

        $writer->process();

        $this->saveFilename($export, $writer);

        $this->logger->notice('Export completed'); // @translate
    }

    /**
     * Save the filename from the writer, if any.
     *
     * @param ExportRepresentation $export
     * @param WriterInterface $writer
     */
    protected function saveFilename(ExportRepresentation $export, WriterInterface $writer): void
    {
        if (!($writer instanceof Parametrizable)) {
            return;
        }

        $params = $writer->getParams();
        if (empty($params['filename'])) {
            return;
        }

        $data = [
            'o:filename' => $params['filename'],
        ];
        $this->api()->update('bulk_exports', $export->id(), $data, [], ['isPartial' => true]);

        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $baseFiles = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $baseUrl = $config['file_store']['local']['base_uri'] ?: $services->get('Router')->getBaseUrl() . '/files';
        $this->logger->notice(
            'The export is available at {url} (size: {size} bytes).', // @translate
            ['url' => $baseUrl . '/bulk_export/' . $params['filename'], 'size' => filesize($baseFiles . '/bulk_export/' .$params['filename'])]
        );
    }

    /**
     * Get the logger for the bulk process (the Omeka one, with reference id).
     *
     * @return \Laminas\Log\Logger
     */
    protected function getLogger()
    {
        if ($this->logger) {
            return $this->logger;
        }
        $this->logger = $this->getServiceLocator()->get('Omeka\Logger');
        $referenceId = new \Laminas\Log\Processor\ReferenceId();
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
     * @return \BulkExport\Writer\WriterInterface
     */
    protected function getWriter()
    {
        $services = $this->getServiceLocator();
        $export = $this->getExport();
        $exporter = $export->exporter();
        $writerClass = $exporter->writerClass();
        $writerManager = $services->get(WriterManager::class);
        if (!$writerManager->has($writerClass)) {
            throw new \Omeka\Job\Exception\InvalidArgumentException((string) new PsrMessage(
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
                $site = $this->api()->read('sites', ['slug' => $siteSlug])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
            }
        } else {
            $defaultSiteId = $services->get('Omeka\Settings')->get('default_site');
            try {
                $site = $this->api()->read('sites', ['id' => $defaultSiteId])->getContent();
                $siteSlug = $site->slug();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
            }
        }

        if (empty($site)) {
            $site = $this->api()->search('sites', ['limit' => 1])->getContent();
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
}
