<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Formatter\FormatterInterface;

/**
 * Base class for Writers that delegate formatting to Formatters.
 *
 * This class implements the wrapper of Writer/Formatter:
 * - Keep their configuration forms and Writer-specific features
 * - Delegate the actual data formatting to a Formatter
 * - Handle Writer-specific concerns: incremental export, include_deleted,
 *   file path management, and job integration
 *
 * @see \BulkExport\Formatter\AbstractFormatter for the Formatter side
 */
abstract class AbstractFormatterWriter extends AbstractWriter
{
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
        'query' => [],
        'incremental' => false,
        'include_deleted' => null,
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

    public function process(): self
    {
        $this
            ->initializeParams()
            ->prepareTempFile();

        if ($this->hasError) {
            return $this;
        }

        // Get all resource IDs to process.
        $resourceIds = $this->getResourceIdsByType();

        // Add deleted resources if HistoryLog is enabled.
        if ($this->hasHistoryLog && $this->options['include_deleted']) {
            $resourceIds = $this->addDeletedResourceIds($resourceIds);
        }

        // Merge all resource types into a single list for the formatter.
        $allResourceIds = array_merge(...array_values($resourceIds));

        if (!count($allResourceIds)) {
            $this->logger->warn('No resources to export.'); // @translate
            return $this;
        }

        $this->stats = [
            'total' => count($allResourceIds),
            'processed' => 0,
            'succeeded' => 0,
            'skipped' => 0,
        ];

        $this->logger->info(
            'Starting export of {total} resources.', // @translate
            ['total' => $this->stats['total']]
        );

        // Get and configure the formatter.
        $this->formatter = $this->getFormatter();
        $this->configureFormatter();

        // Let the formatter process all resources.
        $this->formatter
            ->format($allResourceIds, $this->filepath, $this->getFormatterOptions())
            ->getContent();

        // Get stats from formatter if available.
        if (method_exists($this->formatter, 'getStats')) {
            $formatterStats = $this->formatter->getStats();
            if ($formatterStats) {
                $this->stats = array_merge($this->stats, $formatterStats);
            }
        }

        $this->logger->notice(
            'Export completed: {processed}/{total} resources processed, {succeeded} succeeded, {skipped} skipped.', // @translate
            $this->stats
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

        // Handle incremental export.
        if ($this->options['incremental']) {
            $previousExport = $this->getPreviousExport();
            if ($previousExport) {
                $query['modified_after'] = $previousExport->job()->started()->format('Y-m-d\TH:i:s');
                $this->options['query'] = $query;
            }
        }

        return $this;
    }

    /**
     * Get resource IDs grouped by type.
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
     * Add deleted resource IDs from HistoryLog module.
     */
    protected function addDeletedResourceIds(array $resourceIds): array
    {
        if (!$this->hasHistoryLog) {
            return $resourceIds;
        }

        $this->prepareQueryDelete();

        foreach ($this->options['resource_types'] as $resourceType) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            $deletedIds = $this->api->search('history_events', [
                'entity_name' => $resourceName,
                'operation' => 'delete',
                'distinct_entities' => 'last',
            ] + $this->historyQueryDelete, ['returnScalar' => 'entityId'])->getContent();

            // Add deleted IDs to the resource type's list.
            if ($deletedIds) {
                $resourceIds[$resourceType] = array_merge($resourceIds[$resourceType] ?? [], $deletedIds);
            }
        }

        return $resourceIds;
    }

    /**
     * Prepare the query to get deleted resources.
     */
    protected function prepareQueryDelete(): self
    {
        if (is_array($this->historyQueryDelete)) {
            return $this;
        }

        $query = $this->options['query'];
        unset($query['limit'], $query['offset'], $query['page'], $query['per_page']);

        $this->historyQueryDelete = [];

        $supportedQueryEntityFields = [
            'id' => 'entity_id',
            'created' => 'created',
            'created_before' => 'created_before',
            'created_after' => 'created_after',
            'modified' => 'created',
            'modified_before' => 'created_before',
            'modified_after' => 'created_after',
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
            'Previous completed export is #{export_id} on {date}.', // @translate
            ['export_id' => $previousExport->id(), 'date' => $previousExport->started()]
        );

        return $previousExport;
    }
}
