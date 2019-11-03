<?php
namespace BulkExport\Writer;

use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\WriterInterface;
use Log\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceRepresentation;

abstract class AbstractSpreadsheetWriter extends AbstractWriter
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 100;

    protected $configKeys = [
        'separator',
        'resource_types',
        'metadata',
        // TODO Remove query from the config?
        'query',
    ];

    protected $paramsKeys = [
        'separator',
        'resource_types',
        'metadata',
        'query',
    ];

    /**
     * Type of spreadsheet (default to csv).
     *
     * @var string
     */
    protected $spreadsheetType;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var array
     */
    protected $resourceTypes;

    /**
     * @var array
     */
    protected $stats;

    /**
     * @bool
     */
    protected $jobIsStopped = false;

    public function isValid()
    {
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $destinationDir = $basePath . DIRECTORY_SEPARATOR . 'bulk_export';
        if (!$this->checkDestinationDir($destinationDir)) {
            $this->lastErrorMessage = new PsrMessage(
                'Output directory "{folder}" is not writeable.', // @translate
                ['folder' => $destinationDir]
            );
            return false;
        }
        return parent::isValid();
    }

    public function process()
    {
        $writer = WriterFactory::create($this->spreadsheetType);
        $this->initializeWriter($writer);

        $filepath = $this->prepareTempFile();
        $writer
            ->openToFile($filepath);

        $headers = $this->getHeaders();
        if (!count($headers)) {
            $this->logger->warn('No headers are used in any resources.'); // @translate
            $writer->close();
            $this->saveFile($filepath);
            return;
        }

        $this->stats = [];

        $this->logger->info(
            '{number} different headers are used in all resources.', // @translate
            ['number' => count($headers)]
        );

        $writer
            ->addRow($headers);

        $this->addRows($writer);

        $writer
            ->close();

        $this->saveFile($filepath);
    }

    protected function initializeWriter(WriterInterface $writer)
    {
    }

    protected function addRows(WriterInterface $writer)
    {
        $this->stats['process'] = [];
        $this->stats['totals'] = $this->countResources();
        $this->stats['totalToProcess'] = array_sum($this->stats['totals']);

        if (!$this->stats['totals']) {
            $this->logger->warn('No resource type selected.'); // @translate
            return;
        }

        if (!$this->stats['totalToProcess']) {
            $this->logger->warn('No resource to export.'); // @translate
            return;
        }

        $separator = $this->getParam('separator', '');
        $hasSeparator = strlen($separator) > 0;
        if (!$hasSeparator) {
            $this->logger->warn(
                'No separator selected: only the first value of each property of each resource will be output.' // @translate
            );
        }

        $resourceTypes = $this->getResourceTypes();
        foreach ($resourceTypes as $resourceType) {
            if ($this->jobIsStopped) {
                break;
            }
            $this->addRowsForResource($writer, $resourceType);
        }

        $this->logger->notice(
            'All resources of all resource types ({total}) exported.', // @translate
            ['total' => count($this->stats['process'])]
        );
    }

    protected function addRowsForResource(WriterInterface $writer, $resourceType)
    {
        /**
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Doctrine\ORM\EntityRepository $repository
         * @var \Omeka\Api\Adapter\ItemAdapter $adapter
         */
        $resourceClass = $this->mapResourceTypeToClass($resourceType);
        $apiResource = $this->mapResourceTypeToApiResource($resourceType);
        $resourceText = $this->mapResourceTypeToText($resourceType);
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $connection = $entityManager->getConnection();
        $repository = $entityManager->getRepository($resourceClass);
        $adapter = $services->get('Omeka\ApiAdapterManager')->get($apiResource);
        $api = $services->get('Omeka\ApiManager');

        $headers = $this->getHeaders();
        $separator = $this->getParam('separator', '');
        $hasSeparator = strlen($separator) > 0;

        $query = $this->getParam('query', []);
        if ($query) {
            $queryArray = [];
            parse_str($query, $queryArray);
            $query = $queryArray;
        }

        $this->stats['process'][$resourceType] = [];
        $this->stats['process'][$resourceType]['total'] = $this->stats['totals'][$resourceType];
        $this->stats['process'][$resourceType]['processed'] = 0;
        $this->stats['process'][$resourceType]['succeed'] = 0;
        $this->stats['process'][$resourceType]['skipped'] = 0;
        $stats = &$this->stats['process'][$resourceType];

        $this->logger->notice(
            'Starting export of {total} {resource_type}.', // @translate
            ['total' => $stats['total'], 'resource_type' => $resourceText]
        );

        $offset = 0;
        do {
            if ($this->job->shouldStop()) {
                $this->jobIsStopped = true;
                $this->logger->warn(
                    'The job "Export" was stopped: {processed}/{total} resources processed.', // @translate
                    ['processed' => $stats['processed'], 'total' => $stats['total']]
                );
                break;
            }

            $response = $api
                ->search($apiResource, ['limit' => self::SQL_LIMIT, 'offset' => $offset] + $query);

            // TODO Check other resources (user…).
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources */
            $resources = $response->getContent();
            if (!count($resources)) {
                break;
            }

            // TODO Use SpreadsheetEntry.

            foreach ($resources as $resource) {
                $dataRow = [];
                if ($hasSeparator) {
                    foreach ($headers as $header) {
                        $values = $this->fillHeader($resource, $header, $hasSeparator);
                        // Check if one of the values has the separator.
                        $check = array_filter($values, function ($v) use ($separator) {
                            return strpos((string) $v, $separator) !== false;
                        });
                        if ($check) {
                            $this->logger->warn(
                                'Skipped {resource_type} #{resource_id}: it contains the separator "{separator}".', // @translate
                                ['resource_type' => $resourceText, 'resource_id' => $resource->id(), 'separator' => $separator]
                            );
                            $dataRow = [];
                            break;
                        }
                        $dataRow[] = implode($separator, $values);
                    }
                } else {
                    foreach ($headers as $header) {
                        $values = $this->fillHeader($resource, $header, $hasSeparator);
                        $dataRow[] = (string) reset($values);
                    }
                }

                // Check if data is empty.
                $check = array_filter($dataRow, function ($v) {
                    return (bool) strlen($v);
                });
                if (count($check)) {
                    $writer
                        ->addRow($dataRow);
                    ++$stats['succeed'];
                } else {
                    ++$stats['skipped'];
                }

                // Avoid memory issue.
                unset($resource);

                // Processed = $offset + $key.
                ++$stats['processed'];
            }

            $this->logger->info(
                '{processed}/{total} {resource_type} processed, {succeed} succeed, {skipped} skipped.', // @translate
                ['resource_type' => $resourceText, 'processed' => $stats['processed'], 'total' => $stats['total'], 'succeed' => $stats['succeed'], 'skipped' => $stats['skipped']]
            );

            // Avoid memory issue.
            unset($resources);
            $entityManager->clear();

            $offset += self::SQL_LIMIT;
        } while (true);

        $this->logger->notice(
            '{processed}/{total} {resource_type} processed, {succeed} succeed, {skipped} skipped.', // @translate
            ['resource_type' => $resourceText, 'processed' => $stats['processed'], 'total' => $stats['total'], 'succeed' => $stats['succeed'], 'skipped' => $stats['skipped']]
        );

        $this->logger->notice(
            'End export of {total} {resource_type}.', // @translate
            ['total' => $stats['total'], 'resource_type' => $resourceText]
        );
    }

    /**
     * @return array
     */
    protected function getResourceTypes()
    {
        if (is_null($this->resourceTypes)) {
            $this->resourceTypes = $this->getParam('resource_types', []);
        }
        return $this->resourceTypes;
    }

    /**
     * @return array
     */
    protected function getHeaders()
    {
        if (is_null($this->headers)) {
            $resourceTypes = $this->getResourceTypes();
            $resourceClasses = array_map([$this, 'mapResourceTypeToClass'], $resourceTypes);
            $headers = $this->getParam('metadata', []);
            if ($headers) {
                $index = array_search('properties', $headers);
                $hasProperties = $index !== false;
                if ($hasProperties) {
                    unset($headers[$index]);
                    $headers = array_merge($headers, $this->listUsedProperties($resourceClasses));
                }
            }
            // Currently, default output is all used properties only.
            else {
                $hasProperties = true;
                $headers = $this->listUsedProperties($resourceClasses);
            }

            if ($hasProperties && in_array('oa:Annotation', $resourceTypes)) {
                foreach ($this->listUsedProperties([\Annotate\Entity\AnnotationBody::class]) as $property) {
                    $headers[] = 'oa:hasBody[' . $property . ']';
                }
                foreach ($this->listUsedProperties([\Annotate\Entity\AnnotationTarget::class]) as $property) {
                    $headers[] = 'oa:hasTarget[' . $property . ']';
                }
            }

            if (count($resourceTypes) > 1 && !in_array('resource_type', $headers)) {
                array_unshift($headers, 'resource_type');
            }

            $this->headers = $headers;
        }

        return $this->headers;
    }

    /**
     * Get metadata for a header.
     *
     * @param AbstractResourceRepresentation $resource
     * @param string $header
     * @param bool $hasSeparator
     * @return array Always an array, even for single metadata.
     */
    protected function fillHeader(AbstractResourceRepresentation $resource, $header, $hasSeparator = false)
    {
        switch ($header) {
            // All resources.
            case 'o:id':
                return [$resource->id()];
            case 'resource_type':
                return [$this->mapRepresentationToResourceType($resource)];
            case 'o:resource_template':
                $resourceTemplate = $resource->resourceTemplate();
                return $resourceTemplate ? [$resourceTemplate->label()] : [''];
            case 'o:resource_class':
                $resourceClass = $resource->resourceClass();
                return $resourceClass ? [$resourceClass->term()] : [''];
            case 'o:is_public':
                return method_exists($resource, 'isPublic')
                    ? $resource->isPublic() ? ['true'] : ['false']
                    : [];
            case 'o:is_open':
                return method_exists($resource, 'isOpen')
                    ? $resource->isOpen() ? ['true'] : ['false']
                    : [];
            case 'o:owner[o:id]':
                $owner = $resource->owner();
                return $owner ? [$owner->id()] : [''];
            case 'o:owner':
            case 'o:owner[o:email]':
                $owner = $resource->owner();
                return $owner ? [$owner->email()] : [''];

            // Item set for item.
            case 'o:item_set[o:id]':
                return $resource->resourceName() === 'items'
                    ? $this->extractResourceIds($resource->itemSets())
                    : [];
            case 'o:item_set[dcterms:identifier]':
            case 'o:item_set[dcterms:title]':
                return $resource->resourceName() === 'items'
                    ? $this->extractFirstValue($resource->itemSets(), $header)
                    : [];

            // Media for item.
            case 'o:media[file]':
            case 'o:media[url]':
                $result = [];
                if ($resource->resourceName() === 'items') {
                    foreach ($resource->media() as $media) {
                        $originalUrl = $media->originalUrl();
                        if ($originalUrl) {
                            $result[] = $originalUrl;
                        }
                    }
                }
                return $result;
            case 'o:media[dcterms:identifier]':
            case 'o:media[dcterms:title]':
                return $resource->resourceName() === 'items'
                    ? $this->extractFirstValue($resource->media(), $header)
                    : [];

            // Item for media.
            case 'o:item[o:id]':
                return $resource->resourceName() === 'media'
                    ? [$resource->item()->id()]
                    : [];
            case 'o:item[dcterms:identifier]':
            case 'o:item[dcterms:title]':
                return $resource->resourceName() === 'media'
                    ? $this->extractFirstValue([$resource->item()], $header)
                    : [];

            // Bodies and targets of annotations.
            case strpos($header, 'oa:hasBody[') === 0:
                return $resource->resourceName() === 'annotations'
                    ? $this->extractFirstValue($resource->bodies(), $header)
                    : [];
            case strpos($header, 'oa:hasTarget[') === 0:
                return $resource->resourceName() === 'annotations'
                    ? $this->extractFirstValue($resource->targets(), $header)
                    : [];

            // All properties for all resources.
            default:
                return $hasSeparator
                    ? $resource->value($header, ['all' => true])
                    : [$resource->value($header)];
        }
    }

    /**
     * Return the id of all resources.
     *
     * @param AbstractResourceRepresentation[] $resources
     * @return array
     */
    protected function extractResourceIds(array $resources)
    {
        return array_map(function ($v) {
            return $v->id();
        }, $resources);
    }

    /**
     * Return the first value of the property all resources.
     *
     * @param AbstractResourceEntityRepresentation[] $resources
     * @param string $header The full header, with a term.
     * @return array
     */
    protected function extractFirstValue(array $resources, $header)
    {
        $result = [];
        $term = trim(substr($header, strpos($header, '[') + 1), '[] ');
        foreach ($resources as $resource) {
            $value = $resource->value($term);
            if ($value) {
                // The value should be a string.
                $v = $value->uri();
                if (!is_string($v)) {
                    $v = $value->valueResource();
                    $v = $v ? $v->id() : $value->value();
                }
                $result[] = $v;
            }
        }
        return $result;
    }

    protected function countResources()
    {
        // TODO Use connection?
        /**
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Doctrine\ORM\EntityRepository $repository
         */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $resourceTypes = $this->getResourceTypes();
        $result = array_flip($resourceTypes);
        foreach ($resourceTypes as $resourceType) {
            $resource = $this->mapResourceTypeToApiResource($resourceType);
            if ($resource) {
                $query = $this->getParam('query', []);
                if (!is_array($query)) {
                    $queryArray = [];
                    parse_str($query, $queryArray);
                    $query = $queryArray;
                }
                $result[$resourceType] = $api->search($resource, ['limit' => 1] + $query)->getTotalResults();
            }
        }

        return $result;
    }

    protected function listUsedProperties(array $resourceClasses = [])
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // List only properties that are used.
        // TODO Limit with the query (via adapter).
        $qb = $connection->createQueryBuilder();
        $qb
            ->select('DISTINCT(CONCAT(vocabulary.prefix, ":", property.local_name)) AS term')
            ->from('value', 'value')
            ->innerJoin('value', 'property', 'property', 'property.id = value.property_id')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'vocabulary.id = property.vocabulary_id')
            // Order by vocabulary and by property id, because Omeka orders them
            // with Dublin Core first.
            ->orderBy('vocabulary.id')
            ->addOrderBy('property.id')
        ;

        if ($resourceClasses) {
            $qb
                ->innerJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
                ->andWhere($qb->expr()->in(
                    'resource.resource_type',
                    array_map([$connection, 'quote'], $resourceClasses)
            ));
        }

        $stmt = $connection->executeQuery($qb, $qb->getParameters());
        $resullt = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $resullt;
    }
}
