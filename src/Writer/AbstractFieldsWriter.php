<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Api\Representation\ExportRepresentation;
use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ResourceFieldsTrait;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractFieldsWriter extends AbstractWriter
{
    use ListTermsTrait;
    use MetadataToStringTrait;
    use ResourceFieldsTrait;

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
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        // Keep query in the config to simplify regular export.
        'query',
        'incremental',
        'include_deleted',
    ];

    protected $paramsKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'query',
        'incremental',
        'include_deleted',
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
        'incremental' => false,
        'include_deleted' => null,
    ];

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

    /**
     * @var bool
     */
    protected $jobIsStopped = false;

    /**
     * @var bool
     */
    protected $hasError = false;

    /**
     * @var bool
     */
    protected $prependFieldNames = false;

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

        if ($this->prependFieldNames) {
            if (isset($this->options['format_fields']) && $this->options['format_fields'] === 'label') {
                $this->prepareFieldLabels();
                $this->writeFields($this->fieldLabels);
            } else {
                $this->writeFields($this->fieldNames);
            }
        }

        $this->stats = [];
        $this->logger->info(
            '{number} different fields are used in all resources.', // @translate
            ['number' => count($this->fieldNames)]
        );

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
        $apiResource = $this->mapResourceTypeToApiResource($resourceType);
        $resourceText = $this->mapResourceTypeToText($resourceType);

        /**
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Doctrine\ORM\EntityRepository $repository
         * @var \Omeka\Api\Adapter\ItemAdapter $adapter
         */
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $adapter = $services->get('Omeka\ApiAdapterManager')->get($apiResource);

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
                ->search($apiResource, ['limit' => self::SQL_LIMIT, 'offset' => $offset] + $this->options['query'], ['finalize' => false]);

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
            'End export of {total} {resource_type}.', // @translate
            ['total' => $statistics['total'], 'resource_type' => $resourceText]
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

        $apiResource = $this->mapResourceTypeToApiResource($resourceType);
        $resourceText = $this->mapResourceTypeToText($resourceType);

        $this->stats['process'][$resourceType] = $this->stats['process'][$resourceType] ?? [];
        $this->stats['process'][$resourceType]['total'] = $this->stats['process'][$resourceType]['total'] ?? $this->stats['totals'][$resourceType];
        $this->stats['process'][$resourceType]['processed'] = $this->stats['process'][$resourceType]['processed'] ?? 0;
        $this->stats['process'][$resourceType]['succeed'] = $this->stats['process'][$resourceType]['succeed'] ?? 0;
        $this->stats['process'][$resourceType]['skipped'] = $this->stats['process'][$resourceType]['skipped'] ?? 0;
        $statistics = &$this->stats['process'][$resourceType];

        $this->logger->notice(
            'Starting export of deleted {resource_type}.', // @translate
            ['resource_type' => $resourceText]
        );

        // Avoid an issue when the query contains a page: there should not be
        // pagination at this point. Page and limit cannot be mixed.
        // @see \Omeka\Api\Adapter\AbstractEntityAdapter::limitQuery().
        unset($this->options['query']['page']);
        unset($this->options['query']['per_page']);

        $deleted = 0;

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

            // Return only deleted resource ids when only id is needed.
            // Of course, if "o:id" is not in the field list, this process is useless.
            $response = $this->api
                // Some modules manage some arguments, so keep initialize.
                ->search('history_events', ['entity_name' => $apiResource, 'operation' => 'delete', 'limit' => self::SQL_LIMIT, 'offset' => $offset] + $this->options['query'], ['returnScalar' => 'entityId', 'finalize' => false]);

            // TODO Check other resources (user…).
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources */
            $resourceIds = $response->getContent();
            if (!count($resourceIds)) {
                break;
            }

            foreach ($resourceIds as $resourceId) {
                $dataResource = [
                    'o:id' => [$resourceId],
                    'operation' => ['delete'],
                ];
                $dataResource = array_intersect_key($dataResource, array_flip($this->fieldNames));
                $this
                    ->writeFields($dataResource);
                ++$statistics['succeed'];
                ++$deleted;
                // Processed = $offset + $key.
                ++$statistics['processed'];
            }
            $offset += self::SQL_LIMIT;
        } while (true);

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
            $values = $this->stringMetadata($resource, $fieldName);
            if ($removeEmptyFields) {
                $values = array_filter($values, 'strlen');
                if (!count($values)) {
                    continue;
                }
            }
            if (isset($dataResource[$fieldName])) {
                $dataResource[$fieldName] = is_array($dataResource[$fieldName])
                    ? array_merge($dataResource[$fieldName], $values)
                    : array_merge([$dataResource[$fieldName]], $values);
            } else {
                $dataResource[$fieldName] = $values;
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

        $query = ['limit' => 0] + $this->options['query'];

        if ($this->includeDeleted) {
            $queryDelete = $query + [
                'operation' => 'delete',
                'distinct_entities' => 'last',
            ];
        }

        foreach ($this->options['resource_types'] as $resourceType) {
            $resource = $this->mapResourceTypeToApiResource($resourceType);
            $result[$resourceType] = $resource
                // Some modules manage some arguments, so keep initialize.
                ? $this->api->search($resource, $query, ['finalize' => false])->getTotalResults()
                : 0;
            if ($this->includeDeleted) {
                $result[$resourceType] += $this->api->search('history_events', $queryDelete , ['finalize' => false])->getTotalResults();
            }
        }

        return $result;
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
            return null;
        }

        return count($previousExports)
            ? reset($previousExports)
            : null;
    }
}
