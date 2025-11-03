<?php declare(strict_types=1);

namespace BulkExport\Job;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Interfaces\Configurable;
use BulkExport\Interfaces\Parametrizable;
use BulkExport\Writer\Manager as WriterManager;
use BulkExport\Writer\WriterInterface;
use Common\Stdlib\PsrMessage;
use Doctrine\DBAL\Connection;
use Laminas\Log\Logger;
use Omeka\Job\AbstractJob;
use ZipArchive;

class Export extends AbstractJob
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \BulkExport\Api\Representation\ExportRepresentation
     */
    protected $export;

    /**
     * @var \BulkExport\Api\Representation\ExporterRepresentation
     */
    protected $exporter;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

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

        // TODO Manage option incremental for zip of files.
        // $zipFiles = $this->exporter->configOption('exporter', 'zip_files');
        $zipFiles = $this->writer->getParam('zip_files', []);
        if ($zipFiles) {
            $resourceTypes = $this->writer->getParam('resource_types', []);
            // TODO Use api names, not rdf names for resource_types.
            $resourceTypesApi = array_intersect($resourceTypes, ['items', 'media', 'o:Item', 'o:Media']);
            if ($resourceTypesApi) {
                $query = $this->writer->getParam('query', []);
                if (!is_array($query)) {
                    $queryArray = [];
                    parse_str((string) $query, $queryArray);
                    $query = $queryArray;
                }
                $this->zipFiles($zipFiles, $resourceTypesApi, $query);
            }
        }

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

    /**
     * Create a zip of files.
     *
     * Adapted:
     * @see \BulkExport\Job\Export::zipFiles()
     * @see \Zip\Job\ZipFiles::perform()
     */
    protected function zipFiles(array $formats, array $resourceTypes, array $query): self
    {
        if (!$formats || !$resourceTypes) {
            return $this;
        }

        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $this->basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->baseUrl = $config['file_store']['local']['base_uri'] ?: $services->get('Router')->getBaseUrl() . '/files';

        $this->connection = $services->get('Omeka\Connection');
        $this->entityManager = $services->get('Omeka\EntityManager');

        // Resource types may be "items" or "media".
        $resourceType = in_array('items', $resourceTypes) || in_array('o:Item', $resourceTypes)
            ? 'items'
            : 'media';

        if ($resourceType === 'items') {
            // TODO item_id require a single id, so use a loop for now. And for now, not possible to use "has_original" or "has_thumbnails" (or use loop): $format === 'original' ? 'has_original' : 'has_thumbnails' => true.
            // TODO Check storage_id early.
            $ids= $this->api->search('items', ['has_media' => true] + $query, ['returnScalar' => 'id'])->getContent();
        } else {
            $ids = $this->api->search('media', ['renderer' => 'file'] + $query, ['returnScalar' => 'id'])->getContent();
        }

        if (!$ids) {
            $this->logger->notice(
                'No resources to create zip file.' // @translate
            );
            return $this;
        }

        foreach ($formats as $format) {
            $this->logger->notice(
                'Creation of zip of all local files ({format})', // @translate
                ['format' => $format]
            );

            // TODO Limit by total number of files by zip.
            $total = $this->zipFilesForType($resourceType, $ids, $format, 0);

            $this->logger->notice(
                'End of creation of zip for format {format} to {download}: {total} files.', // @translate
                // TODO For now, only one file.
                ['format' => $format, 'download' => $this->baseUrl . '/temp/export_' . $format . '_' . $this->job->getId() . '_0001.zip', 'total' => $total]
            );
        }

        return $this;
    }

    /**
     * Create a zip file with all files for the specified format.
     *
     * Format are "original", "large", "medium", "square" and "asset".
     * Special formats are "asset_original", "asset_large", etc. When used, the
     * asset is stored if any, else the thumbnail in the specified format.
     */
    protected function zipFilesForType(string $resourceType, array $ids, string $format, int $by): int
    {
        // Get the list of files matching the ids.
        // Media may not have any file according to hasOriginal() or hasThumbnails().

        // Get the full list of files inside the specified directory.
        $storageNames = $this->listStorageNamesForFormat($resourceType, $ids, $format);
        $totalFiles = count($storageNames);

        if (!$totalFiles) {
            $this->logger->warn(
                'No files to zip for format "{format}".', // @translate
                ['format' => $format]
            );
            return 0;
        }

        // Batch zip the resources in chunks.
        $filesZip = [];
        $index = 0;
        $indexFile = 1;
        $baseFilename = $this->basePath . '/temp/tmp/export_' . $format . '_' . $this->job->getId() . '_';
        $finalBaseFilename = $this->basePath . '/temp/export_' . $format . '_' . $this->job->getId() . '_';
        $totalChunks = $totalFiles && $by ? (int) ceil(count($storageNames) / $by) : 0;

        @mkdir(dirname($baseFilename), 0775, true);

        foreach (array_chunk($storageNames, $by ?: 10000000, true) as $files) {
            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Zipping "{format}" files stopped.', // @translate
                    ['format' => $format]
                );
                foreach ($filesZip as $file) {
                    @unlink($file);
                }
                return 0;
            }

            $filepath = $baseFilename . sprintf('%04d', ++$index) . '.zip';
            $filesZip[] = $filepath;

            @unlink($filepath);
            $zip = new ZipArchive();
            $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $comment = <<<INI
                chunk = $index
                total_chunks = $totalChunks
                total_files = $totalFiles
                INI;
            $zip->setArchiveComment($comment);

            // The path is already relative.
            // The format is prepended now and the extension is the right one.
            foreach ($files as $file) {
                $relativePath = ltrim((string) $file, '/');
                $fullPath = $this->basePath . '/' . $relativePath;
                if (!file_exists($fullPath) || !is_readable($fullPath)) {
                    continue;
                }
                $zip->addFile($fullPath, $relativePath);
                ++$indexFile;
            }

            $zip->close();
        }

        // Remove all old zip files for this type.
        $removeList = glob($finalBaseFilename . '*.zip');
        // $removeList[] = $this->basePath . '/temp/zipfiles.txt';
        foreach ($removeList as $file) {
            @unlink($file);
        }

        // Move temp zip files to final destination.
        foreach ($filesZip as $file) {
            rename($file, str_replace($baseFilename, $finalBaseFilename, $file));
        }

        return count($filesZip);
    }

    /**
     * @todo Use the list of zip files.
     */
    protected function addZipList(): void
    {
        $length = mb_strlen($this->basePath) + 1;
        $list = implode("\n", array_map(function($v) use ($length) {
            return mb_substr($v, $length);
        }, glob($this->basePath . '/zip/*.zip')));
        file_put_contents($this->basePath . '/zip/zipfiles.txt', $list);
    }

    /**
     * Get the list of files for the specified format.
     *
     * Format are "original", "large", "medium", "square" and "asset".
     * Special formats are "asset_original", "asset_large", etc. When used, the
     * asset is stored if any, else the thumbnail in the specified format.
     */
    protected function listStorageNamesForFormat($resourceType, array $ids, string $format): array
    {
        $prefix = $format;
        if (in_array($format, ['original', 'large', 'medium', 'square'])) {
            $sql = <<<'SQL'
                SELECT
                    `id`,
                    CONCAT(:prefix, '/', `storage_id`, '.', `extension`) AS "file"
                FROM `media`
                WHERE `__HAS__` = 1
                  AND `storage_id` IS NOT NULL
                  AND `storage_id` != ""
                  AND `extension` IS NOT NULL
                  AND `extension` != ""
                  AND `__TYPE__` IN (:ids)
                ORDER BY `storage_id` ASC;
                SQL;
            $sql = strtr($sql, [
                '__HAS__' => $format === 'original' ? 'has_original' : 'has_thumbnails',
                '__TYPE__' => $resourceType === 'items' ? 'item_id' : 'id',
            ]);
            return $this->connection->executeQuery(
                $sql,
                ['prefix' => $prefix, 'ids' => $ids],
                ['ids' => Connection::PARAM_INT_ARRAY]
            )->fetchAllKeyValue();
        }

        if ($format === 'asset') {
            $sql = <<<'SQL'
                SELECT
                    `resource`.`id`,
                    CONCAT(:prefix, '/', `asset`.`storage_id`, '.', `asset`.`extension`) AS "file"
                FROM `asset`
                INNER JOIN `resource` ON `resource`.`thumbnail_id` = `asset`.`id`
                WHERE `resource`.`id` IN (:ids)
                ORDER BY `asset`.`storage_id` ASC;
                SQL;
            return $this->connection->executeQuery(
                $sql,
                ['prefix' => $prefix, 'ids' => $ids],
                ['ids' => Connection::PARAM_INT_ARRAY]
            )->fetchAllKeyValue();
        }

        // Complex output for special formats like "asset_original", etc.
        // Prepend the main type: "asset" when asset exists, else fallback
        // original or derivative.
        $fallback = substr($format, 6);
        $prefix = 'asset';

        $sql = <<<'SQL'
            (
                SELECT `resource`.`id`, CONCAT('asset', '/', `asset`.`storage_id`, '.', `asset`.`extension`) AS "file"
                FROM `resource` resource
                INNER JOIN `asset` asset ON resource.`thumbnail_id` = asset.`id`
                WHERE resource.`id` IN (:ids)
            )
            UNION ALL
            (
                SELECT
                    `media`.`__TYPE_ID__` AS `id`,
                    CONCAT(
                        :fallback,
                        '/',
                        `media`.`storage_id`,
                        '.',
                        CASE WHEN :fallback = 'original' THEN `media`.`extension` ELSE 'jpg' END
                    ) AS "file"
                FROM `media` media
                WHERE `media`.`storage_id` IS NOT NULL
                    AND `media`.`storage_id` != ""
                    AND (
                      (:fallback = 'original' AND `media`.`has_original` = 1 AND `media`.`extension` IS NOT NULL AND `media`.`extension` != "")
                      OR (:fallback != 'original' AND `media`.`has_thumbnails` = 1)
                    )
                    AND `media`.`__TYPE__` IN (:ids)
                    AND `media`.`id` NOT IN (
                        SELECT `m2`.`id`
                        FROM `media` m2
                        INNER JOIN `resource` r2 ON r2.`thumbnail_id` IS NOT NULL
                        WHERE r2.`id` = m2.`__TYPE_ID__`
                    )
            )
            ORDER BY `file` ASC;
            SQL;
        $sql = strtr($sql, [
            '__TYPE__' => $resourceType === 'items' ? 'item_id' : 'id',
            '__TYPE_ID__' => $resourceType === 'items' ? 'item_id' : 'id',
        ]);
        return $this->connection->executeQuery(
            $sql,
            ['ids' => $ids, 'fallback' => $fallback],
            ['ids' => Connection::PARAM_INT_ARRAY]
        )->fetchAllKeyValue();
    }
}
