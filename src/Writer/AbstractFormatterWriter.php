<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Formatter\FormatterInterface;
use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ResourceFieldsTrait;
use BulkExport\Traits\ShaperTrait;

/**
 * Base class for Writers that delegate formatting to Formatters.
 *
 * This class implements the wrapper of Writer/Formatter:
 * - Keep their configuration forms and Writer-specific features
 * - Delegate the actual data formatting to a Formatter
 * - Handle Writer-specific concerns: incremental export, include_deleted,
 *   file path management, and job integration
 *
 * Note: Deleted resources (include_deleted) require special handling because
 * they no longer exist in the database. The Formatter processes existing
 * resources, then this Writer appends deleted resource entries directly.
 *
 * @see \BulkExport\Formatter\AbstractFormatter for the Formatter side
 */
abstract class AbstractFormatterWriter extends AbstractWriter
{
    use ListTermsTrait;
    use MetadataToStringTrait;
    use ResourceFieldsTrait;
    use ShaperTrait;

    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 100;

    /**
     * The formatter alias to use (e.g., 'csv', 'ods', 'geojson').
     *
     * @var string
     */
    protected $formatterName;

    /**
     * @var \BulkExport\Formatter\FormatterInterface
     */
    protected $formatter;

    /**
     * @var array
     */
    protected $options = [
        'export_id' => null,
        'exporter_label' => null,
        'export_started' => null,
        'dirpath' => null,
        'filebase' => null,
        'resource_type' => null,
        'resource_types' => [],
        'metadata' => [],
        'metadata_exclude' => [],
        'format_fields' => 'name',
        'format_generic' => 'raw',
        'format_resource' => 'url_title',
        'format_resource_property' => 'dcterms:identifier',
        'format_uri' => 'uri_label',
        'language' => '',
        'only_first' => false,
        'empty_fields' => false,
        'query' => [],
        'zip_files',
        'incremental' => false,
        'include_deleted' => null,
        'value_per_column' => false,
        'column_metadata' => [],
    ];

    /**
     * @var bool
     */
    protected $hasError = false;

    /**
     * @var bool
     */
    protected $jobIsStopped = false;

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
     * @var array
     */
    protected $stats;

    /**
     * Whether this format supports appending deleted resources after the Formatter.
     *
     * CSV, TSV, TXT support appending. ODS, JSON do not (without reopening).
     *
     * @var bool
     */
    protected $supportsAppendDeleted = false;

    public function process(): self
    {
        $this
            ->initializeParams()
            ->prepareTempFile();

        if ($this->hasError) {
            return $this;
        }

        // Initialize stats per resource type (like original Writer).
        $this->stats = [
            'process' => [],
            'totals' => [],
            'totalToProcess' => 0,
        ];

        // Get resource IDs for existing resources (NOT deleted).
        $resourceIdsByType = $this->getResourceIdsByType();

        // Prepare deleted resources info if needed.
        $deletedIdsByType = [];
        if ($this->includeDeleted) {
            $this->prepareLastOperations();
            $deletedIdsByType = $this->getDeletedResourceIdsByType();
        }

        // Count totals per resource type.
        foreach ($this->options['resource_types'] as $resourceType) {
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
            return $this;
        }

        // Merge existing IDs only for the formatter (deleted handled separately).
        $allExistingIds = array_merge(...array_values($resourceIdsByType));

        $this->logger->notice(
            'Starting export of {total} resources.', // @translate
            ['total' => $this->stats['totalToProcess']]
        );

        // Log per resource type.
        foreach ($this->options['resource_types'] as $resourceType) {
            $resourceText = $this->mapResourceTypeToText($resourceType);
            $this->logger->info(
                'Starting export of {total} {resource_type}.', // @translate
                ['total' => $this->stats['totals'][$resourceType], 'resource_type' => $resourceText]
            );
        }

        // Get and configure the formatter.
        $this->formatter = $this->getFormatter();
        $this->configureFormatter();

        // Let the formatter process existing resources only.
        if (count($allExistingIds)) {
            $this->formatter
                ->format($allExistingIds, $this->filepath, $this->getFormatterOptions())
                ->getContent();
        }

        // Get stats from formatter if available.
        if (method_exists($this->formatter, 'getStats')) {
            $formatterStats = $this->formatter->getStats();
            // Update global stats.
            if ($formatterStats) {
                foreach ($this->options['resource_types'] as $resourceType) {
                    $this->stats['process'][$resourceType]['processed'] = $formatterStats['processed'] ?? 0;
                    $this->stats['process'][$resourceType]['succeed'] = $formatterStats['succeeded'] ?? 0;
                    $this->stats['process'][$resourceType]['skipped'] = $formatterStats['skipped'] ?? 0;
                }
            }
        }

        // Handle deleted resources separately (they don't exist in DB).
        if ($this->includeDeleted && $this->supportsAppendDeleted) {
            $this->appendDeletedResources($deletedIdsByType);
        } elseif ($this->includeDeleted && !$this->supportsAppendDeleted) {
            $this->logger->warn(
                'Deleted resources cannot be included in this format. Use CSV or TSV for include_deleted support.' // @translate
            );
        }

        // Log completion per resource type.
        foreach ($this->options['resource_types'] as $resourceType) {
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

        return $this;
    }

    /**
     * Get the formatter instance.
     */
    protected function getFormatter(): FormatterInterface
    {
        return $this->services
            ->get(\BulkExport\Formatter\Manager::class)
            ->get($this->formatterName);
    }

    /**
     * Configure the formatter for batch processing.
     */
    protected function configureFormatter(): void
    {
        // Set job callback for stop checks.
        if ($this->job) {
            $this->formatter->setJobCallback(fn() => $this->job->shouldStop());
        }

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
     * Get options to pass to the formatter.
     *
     * Subclasses can override to add format-specific options.
     */
    protected function getFormatterOptions(): array
    {
        return $this->options;
    }

    /**
     * Initialize parameters from config and params.
     */
    protected function initializeParams(): self
    {
        $this->options = $this->getParams() + $this->options;

        if (!in_array($this->options['format_resource'], ['identifier', 'identifier_id'])) {
            $this->options['format_resource_property'] = null;
        }

        // Parse query string if needed.
        $query = $this->options['query'] ?? [];
        if (!is_array($query)) {
            $queryArray = [];
            parse_str((string) $query, $queryArray);
            $query = $queryArray;
            $this->options['query'] = $query;
        }

        // Handle include_deleted.
        $this->includeDeleted = $this->hasHistoryLog ? $this->options['include_deleted'] : null;

        // Handle incremental export.
        if ($this->options['incremental']) {
            $previousExport = $this->getPreviousExport();
            if ($previousExport) {
                $query['modified_after'] = $previousExport->job()->started()->format('Y-m-d\TH:i:s');
                $this->options['query'] = $query;
            }
        }

        // Log warning for only_first mode.
        $separator = $this->options['separator'] ?? '';
        $this->options['has_separator'] = mb_strlen($separator) > 0;
        $this->options['only_first'] = !$this->options['has_separator'];
        if ($this->options['only_first'] && isset($this->options['separator'])) {
            $this->logger->warn(
                'No separator selected: only the first value of each property of each resource will be output.' // @translate
            );
        }

        return $this;
    }

    /**
     * Get resource IDs grouped by type (existing resources only).
     */
    protected function getResourceIdsByType(): array
    {
        $result = [];
        $query = $this->options['query'] ?? [];
        unset($query['limit'], $query['offset'], $query['page'], $query['per_page']);

        foreach ($this->options['resource_types'] as $resourceType) {
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

        foreach ($this->options['resource_types'] as $resourceType) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            $result[$resourceType] = array_keys(
                $this->historyLastOperations[$resourceName] ?? [],
                'delete'
            );
        }

        return $result;
    }

    /**
     * Append deleted resources to the output file.
     *
     * This method must be overridden by formats that support appending.
     *
     * @param array $deletedIdsByType Deleted IDs grouped by resource type.
     */
    protected function appendDeletedResources(array $deletedIdsByType): void
    {
        // Default: do nothing. Override in subclasses that support appending.
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
            foreach ($this->options['resource_types'] as $resourceType) {
                $resourceName = $this->mapResourceTypeToApiResource($resourceType);
                $this->historyLastOperations[$resourceName] = [];
            }
            return $this;
        }

        $this->prepareQueryDelete();

        foreach ($this->options['resource_types'] as $resourceType) {
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
     * Get the previous completed export for incremental processing.
     */
    protected function getPreviousExport(): ?ExportRepresentation
    {
        $export = $this->getExport();
        if (!$export) {
            return null;
        }

        $exporter = $export->exporter();
        $job = $export->job();
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
}
