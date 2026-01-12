<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ResourceFieldsTrait;
use BulkExport\Traits\ShaperTrait;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractFieldsWriter extends AbstractWriter
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

    protected $configKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        // Keep query in the config to simplify regular export.
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
        'value_per_column',
        'column_metadata',
    ];

    protected $paramsKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
        'value_per_column',
        'column_metadata',
    ];

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
     * @var array
     */
    protected $historyLastOperations;

    /**
     * @var array
     */
    protected $historyQueryDelete;

    /**
     * @var bool
     */
    protected $jobIsStopped = false;

    /**
     * @var bool
     */
    protected $prependFieldNames = false;

    /**
     * Json resource types.
     *
     * @var array
     */
    protected $resourceTypes = [];

    /**
     * @var array
     */
    protected $stats;

    public function process(): self
    {
        $this
            ->initializeParams()
            ->prepareTempFile()
            ->initializeOutput();

        if ($this->hasError) {
            return $this;
        }

        $this
            ->prepareFieldNames($this->options['metadata'], $this->options['metadata_exclude']);

        if (!count($this->fieldNames)) {
            $this->logger->warn('No headers are used in any resources.'); // @translate
            $this
                ->finalizeOutput()
                ->saveFile();
            return $this;
        }

        // Pre-scan for value_per_column mode to calculate max column counts.
        if ($this->isValuePerColumnMode()) {
            $this->logger->info('Pre-scanning resources for value_per_column mode...'); // @translate
            $resourceIds = $this->getResourceIdsByType();
            $this->prescanResourcesForColumns($resourceIds);
            $this->expandFieldNamesForColumns();
            $this->logger->info(
                'Pre-scan complete. Expanded to {count} columns.', // @translate
                ['count' => count($this->getExpandedFieldNames())]
            );
        }

        if ($this->prependFieldNames) {
            // Use expanded field names for headers in value_per_column mode.
            $headerFields = $this->isValuePerColumnMode()
                ? $this->getExpandedFieldNames()
                : $this->fieldNames;

            if (isset($this->options['format_fields']) && $this->options['format_fields'] === 'label') {
                $this->prepareFieldLabels();
                // For value_per_column, we use the expanded names directly as labels.
                $headerLabels = $this->isValuePerColumnMode()
                    ? $this->getExpandedFieldNames()
                    : $this->fieldLabels;
                $this->writeFields($headerLabels);
            } else {
                $this->writeFields($headerFields);
            }
        }

        $this->stats = [];
        $this->logger->info(
            '{number} different fields are used in all resources.', // @translate
            ['number' => count($this->fieldNames)]
        );

        if ($this->hasHistoryLog
            && (in_array('operation', $this->fieldNames) || $this->includeDeleted)
        ) {
            $this->prepareLastOperations();
        }

        $this->appendResources();

        $this
            ->finalizeOutput()
            ->saveFile();
        return $this;
    }

    protected function initializeParams(): self
    {
        // Merge params for simplicity.
        $this->options = $this->getParams() + $this->options;

        if (!in_array($this->options['format_resource'], ['identifier', 'identifier_id'])) {
            $this->options['format_resource_property'] = null;
        }

        // TODO Don't remove limit/offset/page/per_page early?
        $query = $this->options['query'] ?? [];
        if (!is_array($query)) {
            $queryArray = [];
            parse_str((string) $query, $queryArray);
            $query = $queryArray;
            $this->options['query'] = $query;
        }

        $this->includeDeleted = $this->hasHistoryLog ? $this->options['include_deleted'] : null;

        if ($this->options['incremental']) {
            $previousExport = $this->getPreviousExport();
            if ($previousExport) {
                // Use a standard query for compatibility.
                // TODO Some resources may have been added during job, so don't add them in incremental export twice.
                // TODO Remove one second because this is "greater than", not equal?
                // It is not possible to search on "created" and "modified":
                // this is a "and", but a "or" is needed.
                // $query['created_after'] = $previousExport->job()->started()->format('Y-m-d\TH:i:s');
                $query['modified_after'] = $previousExport->job()->started()->format('Y-m-d\TH:i:s');
                $this->options['query'] = $query;
            }
        }

        return $this;
    }

    protected function initializeOutput(): self
    {
        return $this;
    }

    /**
     * @param array $fields If fields contains arrays, this method should manage
     * them.
     */
    abstract protected function writeFields(array $fields): self;

    protected function finalizeOutput(): self
    {
        return $this;
    }

    protected function appendResources(): self
    {
        $this->stats['process'] = [];
        $this->stats['totals'] = $this->countResources();
        $this->stats['totalToProcess'] = array_sum($this->stats['totals']);

        if (!$this->stats['totals']) {
            $this->logger->warn('No resource type selected.'); // @translate
            return $this;
        }

        if (!$this->stats['totalToProcess']) {
            $this->logger->warn('No resource to export.'); // @translate
            return $this;
        }

        foreach ($this->options['resource_types'] as $resourceType) {
            if ($this->jobIsStopped) {
                break;
            }
            $this->appendResourcesForResourceType($resourceType);
            if ($this->includeDeleted) {
                $this->appendResourcesDeletedForResourceType($resourceType);
            }
        }

        $this->logger->notice(
            'All resources of all resource types ({total}) exported.', // @translate
            ['total' => count($this->stats['process'])]
        );
        return $this;
    }

    protected function appendResourcesForResourceType($resourceType): self
    {
        $resourceName = $this->mapResourceTypeToApiResource($resourceType);
        $resourceText = $this->mapResourceTypeToText($resourceType);

        /**
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Doctrine\ORM\EntityRepository $repository
         * @var \Omeka\Api\Adapter\ItemAdapter $adapter
         */
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $adapter = $services->get('Omeka\ApiAdapterManager')->get($resourceName);

        $this->stats['process'][$resourceType] = [];
        $this->stats['process'][$resourceType]['total'] = $this->stats['totals'][$resourceType];
        $this->stats['process'][$resourceType]['processed'] = 0;
        $this->stats['process'][$resourceType]['succeed'] = 0;
        $this->stats['process'][$resourceType]['skipped'] = 0;
        $statistics = &$this->stats['process'][$resourceType];

        $this->logger->notice(
            'Starting export of {total} {resource_type}.', // @translate
            ['total' => $statistics['total'], 'resource_type' => $resourceText]
        );

        // Avoid an issue when the query contains a page: there should not be
        // pagination at this point. Page and limit cannot be mixed.
        // @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery().
        unset($this->options['query']['page']);
        unset($this->options['query']['per_page']);

        $offset = 0;
        do {
            if ($this->job->shouldStop()) {
                $this->jobIsStopped = true;
                $this->logger->warn(
                    'The job "Export" was stopped: {processed}/{total} resources processed.', // @translate
                    ['processed' => $statistics['processed'], 'total' => $statistics['total']]
                );
                break;
            }

            $response = $this->api
                // Some modules manage some arguments, so keep initialize.
                ->search($resourceName, [
                    'limit' => self::SQL_LIMIT,
                    'offset' => $offset,
                ] + $this->options['query'], ['finalize' => false]);

            // TODO Check other resources (user…).
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources */
            $resources = $response->getContent();
            if (!count($resources)) {
                break;
            }

            // TODO Use SpreadsheetEntry.

            foreach ($resources as $resource) {
                $resource = $adapter->getRepresentation($resource);

                $dataResource = $this->getDataResource($resource);

                // Check if data is empty.
                $check = array_filter($dataResource, function ($v) {
                    return is_array($v) ? count($v) : mb_strlen($v);
                });
                if (count($check)) {
                    $this
                        ->writeFields($dataResource);
                    ++$statistics['succeed'];
                } else {
                    ++$statistics['skipped'];
                }

                // Avoid memory issue.
                unset($resource);

                // Processed = $offset + $key.
                ++$statistics['processed'];
            }

            $this->logger->info(
                '{processed}/{total} {resource_type} processed, {succeed} succeed, {skipped} skipped.', // @translate
                ['resource_type' => $resourceText, 'processed' => $statistics['processed'], 'total' => $statistics['total'], 'succeed' => $statistics['succeed'], 'skipped' => $statistics['skipped']]
            );

            // Avoid memory issue.
            unset($resources);
            $entityManager->clear();

            $offset += self::SQL_LIMIT;
        } while (true);

        $this->logger->notice(
            '{processed}/{total} {resource_type} processed, {succeed} succeed, {skipped} skipped.', // @translate
            ['resource_type' => $resourceText, 'processed' => $statistics['processed'], 'total' => $statistics['total'], 'succeed' => $statistics['succeed'], 'skipped' => $statistics['skipped']]
        );

        $this->logger->notice(
            'End export of {resource_type}.', // @translate
            ['resource_type' => $resourceText]
        );

        return $this;
    }

    protected function appendResourcesDeletedForResourceType($resourceType): self
    {
        if (!in_array('o:id', $this->fieldNames)) {
            $this->logger->warn(
                'The deleted resources cannot be output when the internal id is not included in the list of fields.' // @translate
            );
            return $this;
        }

        $resourceName = $this->mapResourceTypeToApiResource($resourceType);
        $resourceText = $this->mapResourceTypeToText($resourceType);

        $this->stats['process'][$resourceType] ??= [];
        $this->stats['process'][$resourceType]['total'] ??= $this->stats['totals'][$resourceType];
        $this->stats['process'][$resourceType]['processed'] ??= 0;
        $this->stats['process'][$resourceType]['succeed'] ??= 0;
        $this->stats['process'][$resourceType]['skipped'] ??= 0;
        $statistics = &$this->stats['process'][$resourceType];

        $this->logger->notice(
            'Starting export of deleted {resource_type}.', // @translate
            ['resource_type' => $resourceText]
        );

        $deleted = 0;

        foreach (array_keys($this->historyLastOperations[$resourceName], 'delete') as $resourceId) {
            $dataResource = [
                'o:id' => [$resourceId],
                'operation' => ['delete'],
            ];
            $dataResource = array_intersect_key($dataResource, array_flip($this->fieldNames));
            $this
                ->writeFields($dataResource);
            ++$statistics['succeed'];
            ++$deleted;
            ++$statistics['processed'];
        }

        if ($deleted) {
            $this->logger->notice(
                'End export of {total} deleted {resource_type}.', // @translate
                ['total' => $deleted, 'resource_type' => $resourceText]
            );
        } else {
            $this->logger->notice(
                'No deleted resource exported for {resource_type}.', // @translate
                ['resource_type' => $resourceText]
            );
        }

        return $this;
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): array
    {
        $dataResource = [];
        $removeEmptyFields = !$this->options['empty_fields'];
        foreach ($this->fieldNames as $fieldName) {
            // Get all source fields for this output field (handles merged fields).
            $sourceFields = $this->getSourceFieldsForOutput($fieldName);

            // Get the shaper for this output field (supports multiple shapers per metadata).
            $outputShaper = $this->getShaperForField($fieldName);

            $allValues = [];
            foreach ($sourceFields as $sourceField) {
                // Use output field's shaper if set, otherwise check source field's shaper.
                $shaper = $outputShaper ?? $this->getShaperForField($sourceField);
                $shaperParams = $this->shaperSettings($shaper);
                $values = $this->stringMetadata($resource, $sourceField, $shaperParams);
                $values = $this->shapeValues($values, $shaperParams);
                if ($removeEmptyFields) {
                    $values = array_filter($values, 'strlen');
                }
                $allValues = array_merge($allValues, $values);
            }

            if ($removeEmptyFields && !count($allValues)) {
                continue;
            }

            if (isset($dataResource[$fieldName])) {
                $dataResource[$fieldName] = is_array($dataResource[$fieldName])
                    ? array_merge($dataResource[$fieldName], $allValues)
                    : array_merge([$dataResource[$fieldName]], $allValues);
            } else {
                $dataResource[$fieldName] = $allValues;
            }
        }
        return $dataResource;
    }

    /**
     * Get all resource ids to be processed by resource type.
     *
     * @todo Append deleted resources (but for now only used by geojson).
     */
    protected function getResourceIdsByType(): array
    {
        /** @var \Omeka\Api\Manager $api */
        $result = [];
        foreach ($this->options['resource_types'] as $resourceType) {
            $resource = $this->mapResourceTypeToApiResource($resourceType);
            $result[$resourceType] = $resource
                // Some modules manage some arguments, so keep initialize.
                ? $this->api->search($resource, $this->options['query'], ['returnScalar' => 'id'])->getContent()
                : [];
        }
        return $result;
    }

    protected function countResources(): array
    {
        $result = [];

        $query = $this->options['query'];
        unset($query['limit'], $query['offset'], $query['page'], $query['per_page']);

        if ($this->includeDeleted) {
            $this->prepareLastOperations();
        }

        foreach ($this->options['resource_types'] as $resourceType) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            $result[$resourceType] = $resourceName
                // Some modules manage some arguments, so keep initialize.
                ? $this->api->search($resourceName, $query, ['finalize' => false])->getTotalResults()
                : 0;
            if ($this->includeDeleted) {
                $result[$resourceType] += count(array_keys($this->historyLastOperations[$resourceName], 'delete'));
            }
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

        if (!in_array('operation', $this->fieldNames) && !$this->includeDeleted) {
            foreach ($this->options['resource_types'] as $resourceType) {
                $resourceName = $this->mapResourceTypeToApiResource($resourceType);
                $this->historyLastOperations[$resourceName] = [];
            }
            return $this;
        }

        $query = $this->options['query'];
        unset($query['limit'], $query['offset'], $query['page'], $query['per_page']);

        if ($this->includeDeleted) {
            $this->prepareQueryDelete();
        }

        foreach ($this->options['resource_types'] as $resourceType) {
            $resourceName = $this->mapResourceTypeToApiResource($resourceType);
            if (in_array('operation', $this->fieldNames)) {
                $ids = $resourceName
                    ? $this->api->search($resourceName, $query, ['returnScalar' => 'id'])->getContent()
                    : [];
            } else {
                $ids = [];
            }
            if ($this->includeDeleted) {
                $ids += $this->api->search('history_events', [
                    'entity_name' => $resourceName,
                ] + $this->historyQueryDelete, ['returnScalar' => 'entityId'])->getContent();
            }
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
     *
     * Only id and date are supported in the query for now.
     *
     * @todo Move into module HistoryLog and create a filter.
     */
    protected function prepareQueryDelete(): self
    {
        if (is_array($this->historyQueryDelete)) {
            return $this->historyQueryDelete;
        }

        $query = $this->options['query'];
        unset($query['limit'], $query['offset'], $query['page'], $query['per_page']);

        $this->historyQueryDelete = [
            'operation' => 'delete',
            'distinct_entities' => 'last',
        ];

        // Some supported fields require module Advanced Search.
        $supportedQueryEntityFields = [
            'id' => 'entity_id',
            'created' => 'created',
            'created_before' => 'created_before',
            'created_after' => 'created_after',
            'created_before_on' => 'created_before_on',
            'created_after_on' => 'created_after_on',
            'created_until' => 'created_until',
            'created_since' => 'created_since',
            // History logs are not modifiable.
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

    protected function getPreviousExport(): ?ExportRepresentation
    {
        $export = $this->getExport();
        if (!$export) {
            return null;
        }

        $exporter = $export->exporter();
        if (!$exporter) {
            return null;
        }

        $job = $export->job();
        if (!$job) {
            return null;
        }

        $user = $job->owner();
        if (!$user) {
            return null;
        }

        try {
            // By construction, the current job is not completed, so get first
            // similar export.
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
            ['username' => $user->name(), 'exporter_label' => $exporter->label(), 'export_id' => $previousExport->id(), 'date' => $previousExport->started()]
        );

        return $previousExport;
    }
}
