<?php declare(strict_types=1);

namespace BulkExport\Job;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Formatter\FormatterInterface;
use BulkExport\Formatter\Manager as FormatterManager;
use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ResourceFieldsTrait;
use Common\Stdlib\PsrMessage;
use Doctrine\DBAL\Connection;
use Laminas\Log\Logger;
use Omeka\Job\AbstractJob;
use ZipArchive;

/**
 * Export job that calls Formatters directly.
 *
 * Handles all job-specific concerns:
 * - Resource gathering (existing and deleted)
 * - Incremental export (modified_after query)
 * - HistoryLog integration (include_deleted)
 * - File management (temp file, output path, save)
 * - Stats tracking and progress logging
 */
class Export extends AbstractJob
{
    use ListTermsTrait;
    use MetadataToStringTrait;
    use ResourceFieldsTrait;

    const SQL_LIMIT = 100;

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
     * @var \BulkExport\Formatter\FormatterInterface
     */
    protected $formatter;

    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var string
     */
    protected $filepath;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $stats;

    /**
     * @var bool
     */
    protected $hasHistoryLog = false;

    /**
     * @var bool
     */
    protected $includeDeleted = false;

    /**
     * @var array
     */
    protected $historyLastOperations;

    /**
     * @var array
     */
    protected $historyQueryDelete;

    /**
     * Formats that support appending deleted resources.
     */
    protected $formatsWithAppendDeleted = ['csv', 'tsv', 'txt'];

    /**
     * @var int|null Filesize stored before temp file deletion (for cloud storage).
     */
    protected $lastSavedFilesize;

    public function perform(): void
    {
        $this->services = $this->getServiceLocator();
        $this->api = $this->services->get('Omeka\ApiManager');
        $this->logger = $this->services->get('Omeka\Logger');

        // Check HistoryLog module availability.
        $moduleManager = $this->services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('HistoryLog');
        $this->hasHistoryLog = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;

        $bulkExportId = $this->getArg('bulk_export_id');
        if (!$bulkExportId) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err('Export record id is not set.'); // @translate
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
            $entityManager = $this->services->get('Omeka\EntityManager');
            $newExport = $this->export->jsonSerialize();
            $newExport = array_diff_key($newExport, array_flip(['@id', 'o:id', 'o:job']));
            $newExport['o-bulk:exporter'] = $entityManager->getReference(\BulkExport\Entity\Exporter::class, $this->exporter->id());
            $newExport['o:owner'] = $this->services->get('Omeka\AuthenticationService')->getIdentity();
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

        if ($this->job->getId()) {
            // Use a reference to avoid Doctrine cascade persist issues when the
            // job entity might be detached after api operations.
            $entityManager = $this->services->get('Omeka\EntityManager');
            $jobReference = $entityManager->getReference(\Omeka\Entity\Job::class, $this->job->getId());
            $this->api->update('bulk_exports', $this->export->id(), ['o:job' => $jobReference], [], ['isPartial' => true]);
        }

        // Get formatter directly.
        $this->formatter = $this->getFormatter();
        if (!$this->formatter) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err(
                'Formatter "{formatter}" is not available.', // @translate
                ['formatter' => $this->exporter->formatterName() ?? 'N/A']
            );
            return;
        }

        // Initialize options from exporter config and export params.
        $this->initializeOptions();

        // Validate output path.
        if (!$this->isValid()) {
            throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                'Export error: {error}', // @translate
                ['error' => 'Output directory is not writable']
            ));
        }

        $this->logger->log(Logger::NOTICE, 'Export started'); // @translate

        // TODO Remove checking routes in Omeka v3.1.
        // Avoid a fatal error with background job when there is no route.
        // Prepare route and site settings.
        $siteSlug = $this->options['site_slug'] ?? null;
        $this->prepareRouteMatchAndSiteSettings($siteSlug);
        $this->prepareServerUrl();

        // Process the export.
        $this->processExport();

        // TODO Manage option incremental for zip of files.
        // Handle zip files if requested.
        $zipFiles = $this->options['zip_files'] ?? [];
        if ($zipFiles) {
            // TODO Use api names, not rdf names for resource_types.
            $resourceTypes = $this->options['resource_types'] ?? [];
            $resourceTypesApi = array_intersect($resourceTypes, ['items', 'media', 'o:Item', 'o:Media']);
            if ($resourceTypesApi) {
                $query = $this->options['query'] ?? [];
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
     * Get the formatter instance.
     */
    protected function getFormatter(): ?FormatterInterface
    {
        $formatterName = $this->exporter->formatterName();
        if (!$formatterName) {
            return null;
        }

        $formatterManager = $this->services->get(FormatterManager::class);
        if (!$formatterManager->has($formatterName)) {
            return null;
        }

        return $formatterManager->get($formatterName);
    }

    /**
     * Initialize options from exporter config and export params.
     */
    protected function initializeOptions(): void
    {
        $formatterConfig = $this->exporter->formatterConfig();
        $formatterParams = $this->export->formatterParams();

        $this->options = array_merge($formatterConfig, $formatterParams);
        $this->options['export_id'] = $this->export->id();
        $this->options['exporter_label'] = $this->exporter->label();
        $this->options['export_started'] = $this->export->started();

        // Parse query string if needed.
        $query = $this->options['query'] ?? [];
        if (!is_array($query)) {
            $queryArray = [];
            parse_str((string) $query, $queryArray);
            $query = $queryArray;
            $this->options['query'] = $query;
        }

        // Handle include_deleted.
        $this->includeDeleted = $this->hasHistoryLog && !empty($this->options['include_deleted']);

        // Handle incremental export.
        if (!empty($this->options['incremental'])) {
            $previousExport = $this->getPreviousExport();
            if ($previousExport) {
                $query['modified_after'] = $previousExport->job()->started()->format('Y-m-d\\TH:i:s');
                $this->options['query'] = $query;
            }
        }

        // Handle separator.
        $separator = $this->options['separator'] ?? '';
        $this->options['has_separator'] = mb_strlen($separator) > 0;
        $this->options['only_first'] = !$this->options['has_separator'];
        if ($this->options['only_first'] && isset($this->options['separator'])) {
            $this->logger->warn(
                'No separator selected: only the first value of each property of each resource will be output.' // @translate
            );
        }
    }

    /**
     * Process the export: gather resources and call formatter.
     */
    protected function processExport(): void
    {
        $this->prepareTempFile();

        // Initialize stats.
        $this->stats = [
            'process' => [],
            'totals' => [],
            'totalToProcess' => 0,
        ];

        // Get resource IDs for existing resources.
        $resourceIdsByType = $this->getResourceIdsByType();

        // Prepare deleted resources info if needed.
        $deletedIdsByType = [];
        if ($this->includeDeleted) {
            $this->prepareLastOperations();
            $deletedIdsByType = $this->getDeletedResourceIdsByType();
        }

        // Count totals per resource type.
        $resourceTypes = $this->options['resource_types'] ?? [];
        foreach ($resourceTypes as $resourceType) {
            $existingCount = count($resourceIdsByType[$resourceType] ?? []);
            $deletedCount = count($deletedIdsByType[$resourceType] ?? []);
            $this->stats['totals'][$resourceType] = $existingCount + $deletedCount;
            $this->stats['process'][$resourceType] = [
                'total' => $existingCount + $deletedCount,
                'processed' => 0,
                'succeed' => 0,
                'skipped' => 0,
            ];
        }
        $this->stats['totalToProcess'] = array_sum($this->stats['totals']);

        if (!$this->stats['totalToProcess']) {
            $this->logger->warn('No resources to export.'); // @translate
            return;
        }

        // Merge existing IDs only for the formatter.
        $allExistingIds = array_merge(...array_values($resourceIdsByType));

        $this->logger->notice(
            'Starting export of {total} resources.', // @translate
            ['total' => $this->stats['totalToProcess']]
        );

        // Log per resource type.
        foreach ($resourceTypes as $resourceType) {
            $resourceText = $this->mapResourceTypeToText($resourceType);
            $this->logger->info(
                'Starting export of {total} {resource_type}.', // @translate
                ['total' => $this->stats['totals'][$resourceType], 'resource_type' => $resourceText]
            );
        }

        // Configure formatter.
        $this->configureFormatter();

        // Let the formatter process existing resources.
        if (count($allExistingIds)) {
            $this->formatter
                ->format($allExistingIds, $this->filepath, $this->options)
                ->getContent();
        }

        // Get stats from formatter if available.
        if (method_exists($this->formatter, 'getStats')) {
            $formatterStats = $this->formatter->getStats();
            if ($formatterStats) {
                foreach ($resourceTypes as $resourceType) {
                    $this->stats['process'][$resourceType]['processed'] = $formatterStats['processed'] ?? 0;
                    $this->stats['process'][$resourceType]['succeed'] = $formatterStats['succeeded'] ?? 0;
                    $this->stats['process'][$resourceType]['skipped'] = $formatterStats['skipped'] ?? 0;
                }
            }
        }

        // Handle deleted resources separately.
        $formatterName = $this->exporter->formatterName();
        $supportsAppendDeleted = in_array($formatterName, $this->formatsWithAppendDeleted);
        if ($this->includeDeleted && $supportsAppendDeleted) {
            $this->appendDeletedResources($deletedIdsByType);
        } elseif ($this->includeDeleted && !$supportsAppendDeleted) {
            $this->logger->warn(
                'Deleted resources cannot be included in this format. Use CSV or TSV for include_deleted support.' // @translate
            );
        }

        // Log completion per resource type.
        foreach ($resourceTypes as $resourceType) {
            $resourceText = $this->mapResourceTypeToText($resourceType);
            $stats = $this->stats['process'][$resourceType];
            $this->logger->notice(
                '{processed}/{total} {resource_type} processed, {succeed} succeed, {skipped} skipped.', // @translate
                [
                    'resource_type' => $resourceText,
                    'processed' => $stats['processed'],
                    'total' => $stats['total'],
                    'succeed' => $stats['succeed'],
                    'skipped' => $stats['skipped'],
                ]
            );
        }

        $this->logger->notice(
            'Export completed: {total} resources processed.', // @translate
            ['total' => $this->stats['totalToProcess']]
        );

        $this->saveFile();
    }

    /**
     * Configure the formatter for batch processing.
     */
    protected function configureFormatter(): void
    {
        // Set job callback for stop checks.
        $this->formatter->setJobCallback(fn() => $this->shouldStop());

        // Set progress callback for logging.
        $this->formatter->setProgressCallback(function ($processed, $total, $stats) {
            $this->logger->info(
                '{processed}/{total} resources processed.', // @translate
                ['processed' => $processed, 'total' => $total]
            );
        });

        // Set batch size.
        $this->formatter->setBatchSize(self::SQL_LIMIT);
    }

    /**
     * Get resource IDs grouped by type (existing resources only).
     */
    protected function getResourceIdsByType(): array
    {
        $result = [];
        $query = $this->options['query'] ?? [];
        unset($query['limit'], $query['offset'], $query['page'], $query['per_page']);

        $resourceTypes = $this->options['resource_types'] ?? [];
        foreach ($resourceTypes as $resourceType) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            $result[$resourceType] = $resourceName
                ? $this->api->search($resourceName, $query, ['returnScalar' => 'id'])->getContent()
                : [];
        }

        return $result;
    }

    /**
     * Get deleted resource IDs grouped by type from HistoryLog.
     */
    protected function getDeletedResourceIdsByType(): array
    {
        if (!$this->hasHistoryLog || !$this->includeDeleted) {
            return [];
        }

        $result = [];
        $this->prepareQueryDelete();

        $resourceTypes = $this->options['resource_types'] ?? [];
        foreach ($resourceTypes as $resourceType) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            $result[$resourceType] = array_keys(
                $this->historyLastOperations[$resourceName] ?? [],
                'delete'
            );
        }

        return $result;
    }

    /**
     * Store history log last operations one time for performance.
     */
    protected function prepareLastOperations(): self
    {
        if (is_array($this->historyLastOperations)) {
            return $this;
        }

        $this->historyLastOperations = [];

        if (!$this->includeDeleted) {
            $resourceTypes = $this->options['resource_types'] ?? [];
            foreach ($resourceTypes as $resourceType) {
                $resourceName = $this->mapResourceTypeToApiResource($resourceType);
                $this->historyLastOperations[$resourceName] = [];
            }
            return $this;
        }

        $this->prepareQueryDelete();

        $resourceTypes = $this->options['resource_types'] ?? [];
        foreach ($resourceTypes as $resourceType) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            $ids = $this->api->search('history_events', [
                'entity_name' => $resourceName,
            ] + $this->historyQueryDelete, ['returnScalar' => 'entityId'])->getContent();

            if ($ids) {
                $entityIds = $this->api->search('history_events', [
                    'entity_name' => $resourceName,
                    'entity_id' => $ids,
                    'distinct_entities' => 'last',
                ], ['returnScalar' => 'entityId'])->getContent();
                $operations = $this->api->search('history_events', [
                    'entity_name' => $resourceName,
                    'entity_id' => $ids,
                    'distinct_entities' => 'last',
                ], ['returnScalar' => 'operation'])->getContent();
                $this->historyLastOperations[$resourceName] = array_combine($entityIds, $operations);
            } else {
                $this->historyLastOperations[$resourceName] = [];
            }
        }

        return $this;
    }

    /**
     * Prepare the query to get deleted resources.
     */
    protected function prepareQueryDelete(): self
    {
        if (is_array($this->historyQueryDelete)) {
            return $this;
        }

        $query = $this->options['query'] ?? [];
        unset($query['limit'], $query['offset'], $query['page'], $query['per_page']);

        $this->historyQueryDelete = [
            'operation' => 'delete',
            'distinct_entities' => 'last',
        ];

        $supportedQueryEntityFields = [
            'id' => 'entity_id',
            'created' => 'created',
            'created_before' => 'created_before',
            'created_after' => 'created_after',
            'created_before_on' => 'created_before_on',
            'created_after_on' => 'created_after_on',
            'created_until' => 'created_until',
            'created_since' => 'created_since',
            'modified' => 'created',
            'modified_before' => 'created_before',
            'modified_before_on' => 'created_before_on',
            'modified_after' => 'created_after',
            'modified_after_on' => 'created_after_on',
            'modified_until' => 'created_until',
            'modified_since' => 'created_since',
        ];

        foreach (array_intersect_key($query, $supportedQueryEntityFields) as $field => $data) {
            $this->historyQueryDelete[$supportedQueryEntityFields[$field]] = $data;
        }

        return $this;
    }

    /**
     * Append deleted resources to CSV/TSV files.
     */
    protected function appendDeletedResources(array $deletedIdsByType): void
    {
        $this->prepareFieldNames($this->options['metadata'] ?? [], $this->options['metadata_exclude'] ?? []);

        if (!in_array('o:id', $this->fieldNames)) {
            $this->logger->warn(
                'The deleted resources cannot be output when the internal id is not included in the list of fields.' // @translate
            );
            return;
        }

        $handle = fopen($this->filepath, 'a');
        if (!$handle) {
            $this->logger->err('Unable to append deleted resources to output file.'); // @translate
            return;
        }

        $delimiter = $this->options['delimiter'] ?? ',';
        $enclosure = $this->options['enclosure'] ?? '"';
        $escape = $this->options['escape'] ?? '\\';

        $deleted = 0;

        foreach ($deletedIdsByType as $resourceType => $deletedIds) {
            $resourceText = $this->mapResourceTypeToText($resourceType);

            if (!count($deletedIds)) {
                continue;
            }

            $this->logger->info(
                'Appending {count} deleted {resource_type}.', // @translate
                ['count' => count($deletedIds), 'resource_type' => $resourceText]
            );

            foreach ($deletedIds as $resourceId) {
                $dataResource = [];
                foreach ($this->fieldNames as $fieldName) {
                    if ($fieldName === 'o:id') {
                        $dataResource[] = (string) $resourceId;
                    } elseif ($fieldName === 'operation') {
                        $dataResource[] = 'delete';
                    } else {
                        $dataResource[] = '';
                    }
                }

                fputcsv($handle, $dataResource, $delimiter, $enclosure, $escape);
                ++$deleted;

                $this->stats['process'][$resourceType]['processed']++;
                $this->stats['process'][$resourceType]['succeed']++;
            }
        }

        fclose($handle);

        if ($deleted) {
            $this->logger->notice(
                'Appended {count} deleted resources to export.', // @translate
                ['count' => $deleted]
            );
        }
    }

    /**
     * Get the previous completed export for incremental processing.
     */
    protected function getPreviousExport(): ?ExportRepresentation
    {
        $exporter = $this->exporter;
        $job = $this->export->job();
        $user = $job ? $job->owner() : null;

        if (!$exporter || !$job || !$user) {
            return null;
        }

        try {
            $previousExports = $this->api->search('bulk_exports', [
                'exporter_id' => $exporter->id(),
                'owner_id' => $user->id(),
                'job_status' => \Omeka\Entity\Job::STATUS_COMPLETED,
                'sort_by' => 'id',
                'sort_order' => 'DESC',
                'limit' => 1,
            ])->getContent();
        } catch (\Exception $e) {
            $this->logger->err($e);
            return null;
        }

        if (!count($previousExports)) {
            return null;
        }

        $previousExport = reset($previousExports);

        $this->logger->notice(
            'Previous completed export by user {username} with exporter "{exporter_label}" is #{export_id} on {date}.', // @translate
            [
                'username' => $user->name(),
                'exporter_label' => $exporter->label(),
                'export_id' => $previousExport->id(),
                'date' => $previousExport->started(),
            ]
        );

        return $previousExport;
    }

    /**
     * Prepare a temporary file for output.
     */
    protected function prepareTempFile(): self
    {
        $config = $this->services->get('Config');
        $tempDir = $config['temp_dir'] ?: sys_get_temp_dir();
        $this->filepath = @tempnam($tempDir, 'omk_bke_');
        return $this;
    }

    /**
     * Validate that the output directory is writable.
     */
    protected function isValid(): bool
    {
        $outputPath = $this->getOutputFilepath();
        $destinationDir = dirname($outputPath);
        return $this->checkDestinationDir($destinationDir) !== null;
    }

    /**
     * Check or create the destination folder.
     */
    protected function checkDestinationDir(string $dirPath): ?string
    {
        if (strpos($dirPath, '../') !== false || strpos($dirPath, '..\\') !== false) {
            $this->logger->err(
                'The path should not contain "../".', // @translate
                ['folder' => $dirPath]
            );
            return null;
        }
        if (file_exists($dirPath)) {
            if (!is_dir($dirPath) || !is_writeable($dirPath)) {
                $this->logger->err(
                    'The destination folder "{folder}" is not writeable.', // @translate
                    ['folder' => $dirPath]
                );
                return null;
            }
        } else {
            $result = @mkdir($dirPath, 0775, true);
            if (!$result) {
                $this->logger->err(
                    'The destination folder "{folder}" is not writeable.', // @translate
                    ['folder' => $dirPath]
                );
                return null;
            }
        }
        return $dirPath;
    }

    /**
     * Get the output file path with placeholders.
     */
    protected function getOutputFilepath(): string
    {
        static $outputFilepath;

        if (is_string($outputFilepath)) {
            return $outputFilepath;
        }

        $translator = $this->services->get('MvcTranslator');

        // Prepare placeholders.
        $label = $this->options['exporter_label'] ?? '';
        $label = $this->slugify($label);
        $label = preg_replace('/_+/', '_', $label);
        $formatterName = $this->exporter->formatterName() ?? 'export';
        $exportId = $this->export ? $this->export->id() : '0';
        $date = (new \DateTime())->format('Ymd');
        $time = (new \DateTime())->format('His');
        $user = $this->services->get('Omeka\AuthenticationService')->getIdentity();
        $userId = $user ? $user->getId() : 0;
        $userName = $user
            ? ($this->slugify($user->getName()) ?: $translator->translate('unknown'))
            : $translator->translate('anonymous');

        $placeholders = [
            '{label}' => $label,
            '{exporter}' => $formatterName,
            '{export_id}' => $exportId,
            '{date}' => $date,
            '{time}' => $time,
            '{user_id}' => $userId,
            '{username}' => $userName,
            '{random}' => substr(strtr(base64_encode(random_bytes(128)), ['+' => '', '/' => '', '=' => '']), 0, 6),
            '{exportid}' => $exportId,
            '{userid}' => $userId,
        ];

        $config = $this->services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $destinationDir = $basePath . '/bulk_export';

        $dir = null;
        $formatDirPath = $this->options['dirpath'] ?? null;
        $hasFormatDirPath = !empty($formatDirPath);
        if ($hasFormatDirPath) {
            $dir = strtr($formatDirPath, $placeholders);
            $dir = trim(rtrim($dir, '/\\ '));
            if (mb_substr($dir, 0, 1) !== '/') {
                $dir = OMEKA_PATH . '/' . $dir;
            }
            if ($dir && $dir !== '/' && $dir !== '\\') {
                $destinationDir = $dir;
            } else {
                $this->logger->warn(
                    'The specified dir path "{path}" is invalid. Using default one.', // @translate
                    ['path' => $formatDirPath]
                );
            }
        }

        $formatFilename = $this->options['filebase'] ?? null;
        $hasFormatFilename = !empty($formatFilename);

        $formatFilename = $formatFilename
            ?: ($label ? '{label}-{date}-{time}' : '{exporter}-{date}-{time}');
        $extension = $this->formatter->getExtension();

        $base = strtr($formatFilename, $placeholders);
        if (!$base) {
            $base = $translator->translate('no-name');
        }

        $base = $this->slugify($base, true);

        if ($hasFormatFilename) {
            $outputFilepath = $destinationDir . '/' . $base . '.' . $extension;
        } else {
            $outputFilepath = null;
            $i = 0;
            do {
                $filename = sprintf('%s%s.%s', $base, $i ? '-' . $i : '', $extension);
                $outputFilepath = $destinationDir . '/' . $filename;
            } while (++$i && file_exists($outputFilepath));
        }

        return $outputFilepath;
    }

    /**
     * Transform the given string into a valid filename.
     */
    protected function slugify(string $input, bool $keepCase = false): string
    {
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $slug = $transliterator->transliterate($input);
        } elseif (extension_loaded('iconv')) {
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $input);
        } else {
            $slug = $input;
        }
        $slug = $keepCase ? $slug : mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^a-zA-Z0-9-]+/u', '_', $slug);
        $slug = preg_replace('/-{2,}/', '_', $slug);
        $slug = preg_replace('/-*$/', '', $slug);
        return $slug;
    }

    /**
     * Save the temp file to the final destination.
     *
     * When using the default location (files/bulk_export/), the file is stored
     * using Omeka's file store adapter to support cloud storage (S3, etc.).
     * When using a custom location, local file system is used.
     */
    protected function saveFile(): self
    {
        $config = $this->services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $defaultDestinationDir = $basePath . '/bulk_export';

        $outputFilepath = $this->getOutputFilepath();

        // Check if using default location or custom path.
        $useFileStore = mb_strpos($outputFilepath, $defaultDestinationDir) === 0;

        if ($useFileStore) {
            // Use Omeka's file store for default location (supports S3, etc.).
            $filename = mb_substr($outputFilepath, mb_strlen($defaultDestinationDir) + 1);
            $storagePath = 'bulk_export/' . $filename;

            try {
                // Store filesize before upload (may not be available after for cloud storage).
                $this->lastSavedFilesize = filesize($this->filepath) ?: null;

                /** @var \Omeka\File\Store\StoreInterface $store */
                $store = $this->services->get('Omeka\File\Store');
                $store->put($this->filepath, $storagePath);
                @unlink($this->filepath);
            } catch (\Exception $e) {
                throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                    'Export error when saving "{filename}" (temp file: "{tempfile}"): {exception}', // @translate
                    ['filename' => $filename, 'tempfile' => $this->filepath, 'exception' => $e]
                ));
            }
        } else {
            // Use local file system for custom paths.
            $filename = $outputFilepath;

            try {
                $result = copy($this->filepath, $outputFilepath);
                @unlink($this->filepath);
            } catch (\Exception $e) {
                throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                    'Export error when saving "{filename}" (temp file: "{tempfile}"): {exception}', // @translate
                    ['filename' => $filename, 'tempfile' => $this->filepath, 'exception' => $e]
                ));
            }

            if (!$result) {
                throw new \Omeka\Job\Exception\RuntimeException((string) new PsrMessage(
                    'Export error when saving "{filename}" (temp file: "{tempfile}").', // @translate
                    ['filename' => $filename, 'tempfile' => $this->filepath]
                ));
            }
        }

        // Update export record with filename.
        $data = ['o:filename' => $filename];
        $this->export = $this->api->update('bulk_exports', $this->export->id(), $data, [], ['isPartial' => true])->getContent();

        $fileUrl = $this->export->fileUrl();
        // For cloud storage, filesize may not be available after upload.
        // Use the size from when we saved (stored in $filesize during save).
        $filesize = $this->export->filesize() ?? $this->lastSavedFilesize ?? null;
        if (!$fileUrl) {
            $this->logger->notice(
                'The export is available locally as specified (size: {size} bytes).', // @translate
                ['size' => $filesize]
            );
        } else {
            $this->logger->notice(
                'The export is available at {link} (size: {size} bytes).', // @translate
                [
                    'link' => sprintf('<a href="%1$s" download="%2$s" target="_self">%2$s</a>', $fileUrl, basename($filename)),
                    'size' => $filesize,
                ]
            );
        }

        return $this;
    }

    protected function mapResourceTypeToApiResource($jsonResourceType): ?string
    {
        $mapping = [
            'o:User' => 'users',
            'o:Vocabulary' => 'vocabularies',
            'o:ResourceClass' => 'resource_classes',
            'o:ResourceTemplate' => 'resource_templates',
            'o:Property' => 'properties',
            'o:Item' => 'items',
            'o:Media' => 'media',
            'o:ItemSet' => 'item_sets',
            'o:Module' => 'modules',
            'o:Site' => 'sites',
            'o:SitePage' => 'site_pages',
            'o:Job' => 'jobs',
            'o:Resource' => 'resources',
            'o:Asset' => 'assets',
            'o:ApiResource' => 'api_resources',
            'oa:Annotation' => 'annotations',
        ];
        return $mapping[$jsonResourceType] ?? null;
    }

    protected function mapResourceTypeToText($jsonResourceType): ?string
    {
        $mapping = [
            'o:User' => 'users',
            'o:Vocabulary' => 'vocabularies',
            'o:ResourceClass' => 'resource classes',
            'o:ResourceTemplate' => 'resource templates',
            'o:Property' => 'properties',
            'o:Item' => 'items',
            'o:Media' => 'media',
            'o:ItemSet' => 'item sets',
            'o:Module' => 'modules',
            'o:Site' => 'sites',
            'o:SitePage' => 'site pages',
            'o:Job' => 'jobs',
            'o:Resource' => 'resources',
            'o:Asset' => 'assets',
            'o:ApiResource' => 'api resources',
            'oa:Annotation' => 'annotations',
        ];
        return $mapping[$jsonResourceType] ?? null;
    }

    protected function prepareRouteMatchAndSiteSettings($siteSlug): self
    {
        if ($siteSlug) {
            try {
                $site = $this->api->read('sites', ['slug' => $siteSlug])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $site = null;
            }
        } else {
            $defaultSiteId = $this->services->get('Omeka\Settings')->get('default_site');
            try {
                $site = $this->api->read('sites', ['id' => $defaultSiteId])->getContent();
                $siteSlug = $site->slug();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                $site = null;
            }
        }

        if (empty($site)) {
            $site = $this->api->search('sites', ['limit' => 1])->getContent();
            $site = $site ? reset($site) : null;
            $siteSlug = $site ? $site->slug() : '***';
        }

        /** @var \Laminas\Mvc\MvcEvent $mvcEvent */
        $mvcEvent = $this->services->get('Application')->getMvcEvent();
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

        $siteSettings = $this->services->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site ? $site->id() : 1);

        return $this;
    }

    protected function prepareServerUrl(): self
    {
        /** @var \Laminas\View\Helper\ServerUrl $serverUrl */
        $serverUrl = $this->services->get('ViewHelperManager')->get('ServerUrl');
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
        // To avoid issue with background job, use the view helper.
        $mailer = $this->services->get('Omeka\Mailer');
        $urlHelper = $this->services->get('ViewHelperManager')->get('url');
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

    protected function zipFiles(array $formats, array $resourceTypes, array $query): self
    {
        if (!$formats || !$resourceTypes) {
            return $this;
        }

        $config = $this->services->get('Config');
        $this->basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $this->baseUrl = $config['file_store']['local']['base_uri'] ?: $this->services->get('Router')->getBaseUrl() . '/files';

        $this->connection = $this->services->get('Omeka\Connection');
        $this->entityManager = $this->services->get('Omeka\EntityManager');

        $resourceType = in_array('items', $resourceTypes) || in_array('o:Item', $resourceTypes)
            ? 'items'
            : 'media';

        if ($resourceType === 'items') {
            // TODO item_id require a single id, so use a loop for now. And for now, not possible to use "has_original" or "has_thumbnails" (or use loop): $format === 'original' ? 'has_original' : 'has_thumbnails' => true.
            // TODO Check storage_id early
            $ids = $this->api->search('items', ['has_media' => true] + $query, ['returnScalar' => 'id'])->getContent();
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
