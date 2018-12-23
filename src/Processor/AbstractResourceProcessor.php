<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Traits\ConfigurableTrait;
use BulkImport\Traits\ParametrizableTrait;
use Zend\Form\Form;

abstract class AbstractResourceProcessor extends AbstractProcessor implements Configurable, Parametrizable
{
    use ConfigurableTrait, ParametrizableTrait;

    /**
     * @var string
     */
    protected $resourceType;

    /**
     * @var string
     */
    protected $resourceLabel;

    /**
     * @var string
     */
    protected $configFormClass;

    /**
     * @var string
     */
    protected $paramsFormClass;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var array
     */
    protected $properties;

    /**
     * @var array
     */
    protected $resourceClasses;

    /**
     * @var array
     */
    protected $dataTypes;

    /**
     * @var int
     */
    protected $indexResource = 0;

    /**
     * @var int
     */
    protected $processing = 0;

    /**
     * @var int
     */
    protected $totalIndexResources = 0;

    /**
     * @var int
     */
    protected $totalProcessed = 0;

    /**
     * @var int
     */
    protected $totalErrors = 0;

    public function getResourceType()
    {
        return $this->resourceType;
    }

    public function getLabel()
    {
        return $this->resourceLabel;
    }

    public function getConfigFormClass()
    {
        return $this->configFormClass;
    }

    public function getParamsFormClass()
    {
        return $this->paramsFormClass;
    }

    public function handleConfigForm(Form $form)
    {
        $values = $form->getData();
        $config = new ArrayObject;
        $this->handleFormGeneric($config, $values);
        $this->handleFormSpecific($config, $values);
        $this->setConfig($config);
    }

    public function handleParamsForm(Form $form)
    {
        $values = $form->getData();
        $params = new ArrayObject;
        $this->handleFormGeneric($params, $values);
        $this->handleFormSpecific($params, $values);
        $params['mapping'] = $values['mapping'];
        $this->setParams($params);
    }

    protected function handleFormGeneric(ArrayObject $args, array $values)
    {
        $args['o:resource_template'] = $values['o:resource_template'];
        $args['o:resource_class'] = $values['o:resource_class'];
        $args['o:is_public'] = $values['o:is_public'];
    }

    protected function handleFormSpecific(ArrayObject $args, array $values)
    {
    }

    public function process()
    {
        $base = $this->baseResource();

        $mapping = $this->getParam('mapping', []);
        $mapping = $this->fullMapping($mapping);

        // Filter the mapping to avoid to loop of entry without targets.
        $mapping = array_filter($mapping);

        $multivalueSeparator = $this->reader->getParam('separator' , '');
        $hasMultivalueSeparator = $multivalueSeparator !== '';

        $insert = [];
        foreach ($this->reader as $index => $entry) {
            ++$this->totalIndexResources;
            // The first entry is #1, but the iterator (array) numbered it 0.
            $this->indexResource = $index + 1;
            $this->logger->notice(
                'Processing resource index #{index}', // @translate
                ['index' => $this->indexResource]
            );

            $resource = clone $base;

            foreach ($mapping as $sourceField => $targets) {
                // Check if the entry has a value for this source field.
                if (!isset($entry[$sourceField])) {
                    continue;
                }

                $value = $entry[$sourceField];
                $values = $hasMultivalueSeparator
                    ? explode($multivalueSeparator, $value)
                    : [$value];
                $values = array_map([$this, 'trimUnicode'], $values);
                $values = array_filter($values, 'strlen');
                if (!$values) {
                    continue;
                }

                $this->fillResource($resource, $targets, $values);
            }

            if ($this->checkResource($resource)) {
                ++$this->processing;
                ++$this->totalProcessed;
                $insert[] = $resource->getArrayCopy();
            } else {
                ++$this->totalErrors;
            }
            // Only add every X for batch import.
            if ($this->processing >= self::BATCH) {
                // Batch create.
                $this->createEntities($insert);
                $insert = [];
                $this->processing = 0;
            }
        }
        // Take care of remainder from the modulo check.
        $this->createEntities($insert);

        $this->logger->notice(
            'End of process: {total_resources} resources to process, {total_processed} processed, {total_errors} errors', // @translate
            [
                'total_resources' => $this->totalIndexResources,
                'total_processed' => $this->totalProcessed,
                'total_errors' => $this->totalErrors,
            ]
        );
    }

    protected function baseResource()
    {
        $resource = new ArrayObject;
        $this->baseGeneric($resource);
        $this->baseSpecific($resource);
        return $resource;
    }

    protected function baseGeneric(ArrayObject $resource)
    {
        $resourceTemplateId = $this->getParam('o:resource_template');
        if ($resourceTemplateId) {
            $resource['o:resource_template'] = ['o:id' => $resourceTemplateId];
        }
        $resourceClassId = $this->getParam('o:resource_class');
        if ($resourceClassId) {
            $resource['o:resource_class'] = ['o:id' => $resourceClassId];
        }
        $resource['o:is_public'] = $this->getParam('o:is_public') !== 'false';
    }

    protected function baseSpecific(ArrayObject $resource)
    {
    }

    protected function fillResource(ArrayObject $resource, array $targets, array $values)
    {
        foreach ($targets as $target) {
            switch ($target['target']) {
                case $this->fillProperty($resource, $target, $values):
                    break;
                case $this->fillGeneric($resource, $target, $values):
                    break;
                case $this->fillSpecific($resource, $target, $values):
                    break;
                default:
                    $resource[$target['target']] = array_pop($values);
                    break;
            }
        }
    }

    protected function fillProperty(ArrayObject $resource, $target, array $values)
    {
        if (!isset($target['value']['property_id'])) {
            return false;
        }

        foreach ($values as $value) {
            $resourceValue = $target['value'];
            switch ($resourceValue['type']) {
                // Currently, most of the datatypes are literal.
                case 'literal':
                // case strpos($resourceValue['type'], 'customvocab:') === 0:
                default:
                    $resourceValue['@value'] = $value;
                    break;
                case 'uri':
                case strpos($resourceValue['type'], 'valuesuggest:') === 0:
                    $resourceValue['@id'] = $value;
                    // $resourceValue['o:label'] = null;
                    break;
                case 'resource':
                case 'resource:item':
                case 'resource:itemset':
                case 'resource:media':
                    $resourceValue['value_resource_id'] = $value;
                    $resourceValue['@language'] = null;
                    break;
            }
            $resource[$target['target']][] = $resourceValue;
        }
        return true;
    }

    protected function fillGeneric(ArrayObject $resource, $target, array $values)
    {
        switch ($target['target']) {
            case 'o:resource_template':
                $value = array_pop($values);
                $resource['o:resource_template'] = $value
                    ? ['o:id' => $value]
                    : null;
                return true;
            case 'o:resource_class':
                $value = array_pop($values);
                $resourceClassId = $this->getResourceClass($value);
                if ($resourceClassId) {
                    $resource['o:resource_class'] = ['o:id' => $resourceClassId];
                }
                return true;
            case 'o:is_public':
                $value = array_pop($values);
                $resource['o:is_public'] = in_array(strtolower($value), ['false', 'no', 'off', 'private'])
                    ? false
                    : (bool) $value;
                return true;
        }
        return false;
    }

    protected function fillSpecific(ArrayObject $resource, $target, array $values)
    {
        return false;
    }

    /**
     * Check if a resource is well-formed.
     *
     * @param ArrayObject $resource
     * @return bool
     */
    protected function checkResource(ArrayObject $resource)
    {
        return true;
    }

    /**
     * Process creation of entities.
     *
     * @param array $data
     */
    protected function createEntities(array $data)
    {
        $resourceType = $this->getResourceType();
        $this->createResources($resourceType, $data);
    }

    /**
     * Process creation of resources.
     *
     * @param array $data
     */
    protected function createResources($resourceType, array $data)
    {
        if (!count($data)) {
            return;
        }

        $labels = [
            'items' => 'item',
            'item_sets' => 'item set',
            'media' => 'media',
        ];
        $label = $labels[$resourceType];

        try {
            $resources = $this->api()
                ->batchCreate($resourceType, $data, [], ['continueOnError' => true])->getContent();
            foreach ($resources as $resource) {
                $this->logger->notice(
                    'Created {resource_type} #{resource_id}', // @translate
                    ['resource_type' => $label, 'resource_id' => $resource->id()]
                );
            }
        } catch (\Exception $e) {
            $this->logger->err('Core error: {exception}', ['exception' => $e]);
            ++$this->totalErrors;
        }
    }

    /**
     * Check if a string is a managed term.
     *
     * @param string $term
     * @return bool
     */
    protected function isTerm($term)
    {
        return $this->getProperty($term) !== null;
    }

    /**
     * Get a property by term.
     *
     * @param string $term
     * @return \Omeka\Entity\Property|null
     */
    protected function getProperty($term)
    {
        $properties = $this->getProperties();
        return isset($properties[$term])
            ? $properties[$term]
            : null;
    }

    /**
     * Get all properties by term.
     *
     * @return \Omeka\Entity\Property[]
     */
    protected function getProperties()
    {
        if (isset($this->properties)) {
            return $this->properties;
        }

        $this->properties = [];
        $properties = $this->api()
            ->search('properties', [], ['responseContent' => 'resource'])->getContent();
        foreach ($properties as $property) {
            $term = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $this->properties[$term] = $property;
        }

        return $this->properties;
    }

    /**
     * Check if a string is a managed term for resource class.
     *
     * @param string $term
     * @return bool
     */
    protected function isClassTerm($term)
    {
        return $this->getResourceClass($term) !== null;
    }

    /**
     * Get a resource class by term.
     *
     * @param string $term
     * @return \Omeka\Entity\ResourceClass|null
     */
    protected function getResourceClass($term)
    {
        $resourceClasses = $this->getResourceClasses();
        return isset($resourceClasses[$term])
            ? $resourceClasses[$term]
            : null;
    }

    /**
     * Get all resource classes by term.
     *
     * @return \Omeka\Entity\ResourceClass[]
     */
    protected function getResourceClasses()
    {
        if (isset($this->resourceClasses)) {
            return $this->resourceClasses;
        }

        $this->resourceClasses = [];
        $resourceClasses = $this->api()
            ->search('resource_classes', [], ['responseContent' => 'resource'])->getContent();
        foreach ($resourceClasses as $resourceClass) {
            $term = $resourceClass->getVocabulary()->getPrefix() . ':' . $resourceClass->getLocalName();
            $this->resourceClasses[$term] = $resourceClass;
        }

        return $this->resourceClasses;
    }

    /**
     * @param string $type
     * @return string|null
     */
    protected function getDataType($type)
    {
        $dataTypes = $this->getDataTypes();
        return isset($dataTypes[$type])
            ? $dataTypes[$type]
            : null;
    }

    /**
     * @return array
     */
    protected function getDataTypes()
    {
        if (isset($this->dataTypes)) {
            return $this->dataTypes;
        }

        $dataTypes = $this->getServiceLocator()->get('Omeka\DataTypeManager')
            ->getRegisteredNames();

        // Append the short data types for easier process.
        $this->dataTypes = array_combine($dataTypes, $dataTypes);

        foreach ($dataTypes as $dataType) {
            $pos = strpos($dataType, ':');
            if ($pos === false) {
                continue;
            }
            $short = substr($dataType, $pos + 1);
            if (!is_numeric($short) && !isset($this->dataTypes[$short])) {
                $this->dataTypes[$short] = $dataType;
            }
        }
        return $this->dataTypes;
    }

    /**
     * Add automapped metadata for properties (language and datatype).
     *
     * @param array $mapping
     * @return array Each target is an array with metadata.
     */
    protected function fullMapping(array $mapping)
    {
        $automapFields = $this->getServiceLocator()->get('ViewHelperManager')->get('automapFields');
        $sourceFields = $automapFields(array_keys($mapping), ['output_full_matches' => true]);
        $index = -1;
        foreach ($mapping as $sourceField => $targets) {
            ++$index;
            if (empty($targets)) {
                continue;
            }
            $metadata = $sourceFields[$index];
            $fullTargets = [];
            foreach ($targets as $target) {
                $result = [];
                $result['field'] = $metadata['field'];
                $result['target'] = $target;
                $property = $this->getProperty($target);
                if ($property) {
                    $result['value']['property_id'] = $property->getId();
                    $result['value']['type'] = $this->getDataType($metadata['type']) ?: 'literal';
                    $result['value']['@language'] = $metadata['@language'];
                    $result['value']['is_public'] = true;
                } else {
                    $result['@language'] = $metadata['@language'];
                    $result['type'] = $metadata['type'];
                }
                $fullTargets[] = $result;
            }
            $mapping[$sourceField] = $fullTargets;
        }
        return $mapping;
    }

    /**
     * Trim all whitespaces.
     *
     * @param string $string
     * @return string
     */
    protected function trimUnicode($string)
    {
        return preg_replace('/^[\h\v\s[:blank:][:space:]]+|[\h\v\s[:blank:][:space:]]+$/u', '', $string);
    }

    /**
     * Check if a string seems to be an url.
     *
     * Doesn't use FILTER_VALIDATE_URL, so allow non-encoded urls.
     *
     * @param string $string
     * @return bool
     */
    protected function isUrl($string)
    {
        return strpos($string, 'https:') === 0
            || strpos($string, 'http:') === 0
            || strpos($string, 'ftp:') === 0;
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
}
