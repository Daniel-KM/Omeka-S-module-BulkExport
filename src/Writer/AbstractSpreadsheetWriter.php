<?php
namespace BulkExport\Writer;

use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\WriterInterface;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ListTermsTrait;
use Log\Stdlib\PsrMessage;

abstract class AbstractSpreadsheetWriter extends AbstractWriter
{
    use ListTermsTrait;
    use MetadataToStringTrait;

    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 100;

    protected $configKeys = [
        'separator',
        'format_headers',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        // TODO Remove query from the config?
        'query',
    ];

    protected $paramsKeys = [
        'separator',
        'format_headers',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        'query',
    ];

    protected $options = [
        'separator' => ' | ',
        'has_separator' => true,
        'format_headers' => 'name',
        'format_generic' => 'raw',
        'format_resource' => 'url_title',
        'format_resource_property' => 'dcterms:identifier',
        'format_uri' => 'uri_label',
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
    protected $headerLabels;

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
        $this->translator = $this->getServiceLocator()->get('MvcTranslator');

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

        if ($this->getParam('format_headers', 'name') === 'label') {
            $headers = $this->getHeadersLabels();
        }
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
        $hasSeparator = mb_strlen($separator) > 0;
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
         * @var \Omeka\Api\Manager $api
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
        $hasSeparator = mb_strlen($separator) > 0;

        $formatGeneric = $this->getParam('format_generic', 'string');
        $formatResource = $this->getParam('format_resource', 'url_title');
        $formatResourceProperty = in_array($formatResource, ['identifier', 'identifier_id'])
            ? $this->getParam('format_resource_property', 'dcterms:identifier')
            : null;
        $formatUri = $this->getParam('format_uri', 'uri_label');

        // It's useless, there are params…
        $this->options = [
            'separator' => $separator,
            'has_separator' => $hasSeparator,
            'format_generic' => $formatGeneric,
            'format_resource' => $formatResource,
            'format_resource_property' => $formatResourceProperty,
            'format_uri' => $formatUri,
        ];

        $query = $this->getParam('query') ?: [];
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
                ->search($apiResource, ['limit' => self::SQL_LIMIT, 'offset' => $offset] + $query, ['initialize' => false, 'finalize' => false]);

            // TODO Check other resources (user…).
            /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[] $resources */
            $resources = $response->getContent();
            if (!count($resources)) {
                break;
            }

            // TODO Use SpreadsheetEntry.

            foreach ($resources as $resource) {
                $resource = $adapter->getRepresentation($resource);
                $dataRow = [];
                if ($hasSeparator) {
                    foreach ($headers as $header) {
                        $values = $this->stringMetadata($resource, $header) ?: [];
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
                        $values = $this->stringMetadata($resource, $header);
                        $dataRow[] = (string) reset($values);
                    }
                }

                // Check if data is empty.
                $check = array_filter($dataRow, 'strlen');
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
            $this->resourceTypes = $this->getParam('resource_types') ?: [];
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
            $classes = array_map([$this, 'mapResourceTypeToClass'], $resourceTypes);
            $headers = $this->getParam('metadata') ?: [];
            if ($headers) {
                $index = array_search('properties', $headers);
                $hasProperties = $index !== false;
                if ($hasProperties) {
                    unset($headers[$index]);
                    $headers = array_merge($headers, array_keys($this->getUsedPropertiesByTerm($classes)));
                }
            } else {
                $hasProperties = true;
                $headers = [
                    'o:id',
                    'o:resource_template',
                    'o:resource_class',
                    'o:owner',
                    'o:is_public',
                ];
                if (count($resourceTypes) === 1) {
                    switch (reset($resourceTypes)) {
                        case 'o:ItemSet':
                            $headers[] = 'o:is_open';
                            break;
                        case 'o:Item':
                            $headers[] = 'o:item_set[o:id]';
                            $headers[] = 'o:item_set[dcterms:title]';
                            $headers[] = 'o:media[o:id]';
                            $headers[] = 'o:media[file]';
                            break;
                        case 'o:Media':
                            $headers[] = 'o:item[o:id]';
                            $headers[] = 'o:item[dcterms:identifier]';
                            $headers[] = 'o:item[dcterms:title]';
                            break;
                        case 'oa:Annotation':
                            $headers[] = 'o:resource[o:id]';
                            $headers[] = 'o:resource[dcterms:identifier]';
                            $headers[] = 'o:resource[dcterms:title]';
                            break;
                        default:
                            break;
                    }
                }
                $headers = array_merge($headers, array_keys($this->getUsedPropertiesByTerm($classes)));
            }

            if ($hasProperties && in_array('oa:Annotation', $resourceTypes)) {
                foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationBody::class])) as $property) {
                    $headers[] = 'oa:hasBody[' . $property . ']';
                }
                foreach (array_keys($this->getUsedPropertiesByTerm([\Annotate\Entity\AnnotationTarget::class])) as $property) {
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

    protected function getHeadersLabels()
    {
        if (is_array($this->headerLabels)) {
            return $this->headerLabels;
        }

        $this->headerLabels = [];
        $mapping = [
            'o:id' => $this->translator->translate('id'), // @translate,
            'o:resource_template' => $this->translator->translate('Resource template'), // @translate
            'o:resource_class' => $this->translator->translate('Resource template'), // @translate
            'o:owner' => $this->translator->translate('Owner'), // @translate
            'o:is_public' => $this->translator->translate('Is public'), // @translate
            'o:is_open' => $this->translator->translate('Is open'), // @translate
            'o:item_set[o:id]' => $this->translator->translate('Item set id'), // @translate
            'o:item_set[dcterms:title]' => $this->translator->translate('Item set'), // @translate
            'o:media[o:id]' => $this->translator->translate('Media id'), // @translate
            'o:media[file]' => $this->translator->translate('Media file'), // @translate
            'o:item[o:id]' => $this->translator->translate('Item id'), // @translate
            'o:item[dcterms:identifier]' => $this->translator->translate('Item identifier'), // @translate
            'o:item[dcterms:title]' => $this->translator->translate('Item title'), // @translate
            'o:resource[o:id]' => $this->translator->translate('Resource id'), // @translate
            'o:resource[dcterms:identifier]' => $this->translator->translate('Resource identifier'), // @translate
            'o:resource[dcterms:title]' => $this->translator->translate('Resource title'), // @translate
            'o:resource' => $this->translator->translate('Resource'), // @translate
            'o:item' => $this->translator->translate('Item'), // @translate
            'o:item_set' => $this->translator->translate('Item set'), // @translate
            'o:media' => $this->translator->translate('Media'), // @translate
            'o:annotation' => $this->translator->translate('Annotation'), // @translate
            'o:asset' => $this->translator->translate('Asset'), // @translate
        ];

        foreach ($this->headers as $header) {
            if (isset($mapping[$header])) {
                $this->headerLabels[] = $mapping[$header];
            } elseif (strpos($header, '[')) {
                $base = strtok($header, '[');
                $property = trim(strok('['), ' []');
                $second = isset($mapping[$property])
                    ? $mapping[$property]
                    : $this->translateProperty($property);
                switch ($base) {
                    case 'oa:hasBody':
                        $this->headerLabels[] = sprintf(
                            $this->translator->translate('Annotation body: %s'),  // @translate;
                            $second
                        );
                        break;
                    case 'oa:hasTarget':
                        $this->headerLabels[] = sprintf(
                            $this->translator->translate('Annotation target: %s'),  // @translate;
                            $second
                        );
                        break;
                    default:
                        $first = isset($mapping[$base])
                            ? $mapping[$base]
                            : $base;
                        $this->headerLabels[] = sprintf(
                            '%1$s: %2$s', // @translate
                            $first,
                            $second
                        );
                        break;
                }
            } else {
                $this->headerLabels[] = $this->translateProperty($header);
            }
        }

        return $this->headerLabels;
    }

    protected function countResources()
    {
        // TODO Use connection?
        /**
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Doctrine\ORM\EntityRepository $repository
         * @var \Omeka\Api\Manager $api
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
                $result[$resourceType] = $api->search($resource, ['limit' => 1] + $query, ['initialize' => false, 'finalize' => false])->getTotalResults();
            }
        }
        return $result;
    }
}
