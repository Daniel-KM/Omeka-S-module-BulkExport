<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Entry;
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
     * @var \BulkImport\Mvc\Controller\Plugin\FindResourcesFromIdentifiers
     */
    protected $findResourcesFromIdentifiers;

    /**
     * @var string|int|array
     */
    protected $identifierName;

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
    protected $resourceTemplates;

    /**
     * @var array
     */
    protected $dataTypes;

    /**
     * @var ArrayObject
     */
    protected $base;

    /**
     * @var array
     */
    protected $mapping;

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
    protected $totalSkipped = 0;

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
        $defaults = [
            'o:resource_template' => null,
            'o:resource_class' => null,
            'o:is_public' => null,
            'identifier_name' => null,
            'entries_by_batch' => null,
        ];
        $result = array_intersect_key($values, $defaults) + $args->getArrayCopy() + $defaults;
        $args->exchangeArray($result);
    }

    protected function handleFormSpecific(ArrayObject $args, array $values)
    {
    }

    public function process()
    {
        $this->base = $this->baseResource();

        $mapping = $this->getParam('mapping', []);
        $mapping = $this->fullMapping($mapping);
        // Filter the mapping to avoid to loop of entry without targets.
        $this->mapping = array_filter($mapping);
        unset($mapping);

        $this->prepareIdentifierName();

        $batch = (int) $this->getParam('entries_by_batch') ?: self::ENTRIES_BY_BATCH;

        $insert = [];
        foreach ($this->reader as $index => $entry) {
            ++$this->totalIndexResources;
            // The first entry is #1, but the iterator (array) numbered it 0.
            $this->indexResource = $index + 1;
            $this->logger->notice(
                'Processing resource index #{index}', // @translate
                ['index' => $this->indexResource]
            );

            $resource = $this->processEntry($entry);
            if (!$resource) {
                continue;
            }

            if ($this->checkResource($resource)) {
                ++$this->processing;
                ++$this->totalProcessed;
                $insert[] = $resource->getArrayCopy();
            } else {
                ++$this->totalErrors;
            }

            // Only add every X for batch import.
            if ($this->processing >= $batch) {
                // Batch create.
                $this->createEntities($insert);
                $insert = [];
                $this->processing = 0;
            }
        }
        // Take care of remainder from the modulo check.
        $this->createEntities($insert);

        $this->logger->notice(
            'End of process: {total_resources} resources to process, {total_skipped} skipped, {total_processed} processed, {total_errors} errors inside module.', // @translate
            [
                'total_resources' => $this->totalIndexResources,
                'total_skipped' => $this->totalSkipped,
                'total_processed' => $this->totalProcessed,
                'total_errors' => $this->totalErrors,
            ]
        );
    }

    /**
     * Process one entry to create one resource (and eventually attached ones).
     *
     * @param Entry $entry
     * @return ArrayObject|null
     */
    protected function processEntry(Entry $entry)
    {
        if ($entry->isEmpty()) {
            $this->logger->warn(
                'Resource index #{index} is empty and is skipped.', // @translate
                ['index' => $this->indexResource]
            );
            ++$this->totalSkipped;
            return null;
        }

        $resource = clone $this->base;

        // TODO Manage the multivalue separator at field level.
        $multivalueSeparator = $this->reader->getParam('separator', '');

        foreach ($this->mapping as $sourceField => $targets) {
            // Check if the entry has a value for this source field.
            if (!isset($entry[$sourceField])) {
                continue;
            }

            $value = $entry[$sourceField];
            $values = $multivalueSeparator !== ''
                ? explode($multivalueSeparator, $value)
                : [$value];
            $values = array_map([$this, 'trimUnicode'], $values);
            $values = array_filter($values, 'strlen');
            if (!$values) {
                continue;
            }

            $this->fillResource($resource, $targets, $values);
        }

        return $resource;
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
                $resourceTemplateId =$this->getResourceTemplateId($value);
                if ($resourceTemplateId) {
                    $resource['o:resource_template'] = ['o:id' => $resourceTemplateId];
                }
                return true;
            case 'o:resource_class':
                $value = array_pop($values);
                $resourceClassId =$this->getResourceClassId($value);
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

        try {
            if (count($data) === 1) {
                $resource = $this->api()
                    ->create($resourceType, reset($data))->getContent();
                $resources = [$resource];
            } else {
                $resources = $this->api()
                    ->batchCreate($resourceType, $data, [], ['continueOnError' => true])->getContent();
            }
        } catch (\Exception $e) {
            $this->logger->err('Core error: {exception}', ['exception' => $e]);
            ++$this->totalErrors;
            return;
        }

        $labels = [
            'items' => 'item',
            'item_sets' => 'item set',
            'media' => 'media',
        ];
        $label = $labels[$resourceType];
        foreach ($resources as $resource) {
            $this->logger->notice(
                'Created {resource_type} #{resource_id}', // @translate
                ['resource_type' => $label, 'resource_id' => $resource->id()]
            );
        }
    }

    /**
     * Check if a string or a id is a managed term.
     *
     * @param string|int $termOrId
     * @return bool
     */
    protected function isPropertyTerm($termOrId)
    {
        return $this->getPropertyId($termOrId) !== null;
    }

    /**
     * Get a property id by term or id.
     *
     * @param string|int $termOrId
     * @return int|null
     */
    protected function getPropertyId($termOrId)
    {
        $propertyIds = $this->getPropertyIds();
        return is_numeric($termOrId)
            ? (array_search($termOrId, $propertyIds) ? $termOrId : null)
            : (isset($propertyIds[$termOrId]) ? $propertyIds[$termOrId] : null);
    }

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds()
    {
        if (isset($this->properties)) {
            return $this->properties;
        }

        $this->properties = [];
        $properties = $this->api()
            ->search('properties', [], ['responseContent' => 'resource'])->getContent();
        foreach ($properties as $property) {
            $term = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $this->properties[$term] = $property->getId();
        }

        return $this->properties;
    }

    /**
     * Check if a string or a id is a resource class.
     *
     * @param string|int $termOrId
     * @return bool
     */
    protected function isResourceClass($termOrId)
    {
        return $this->getResourceClassId($termOrId) !== null;
    }

    /**
     * Get a resource class by term or by id.
     *
     * @param string|int $termOrId
     * @return int|null
     */
    protected function getResourceClassId($termOrId)
    {
        $resourceClassIds = $this->getResourceClassIds();
        return is_numeric($termOrId)
            ? (array_search($termOrId, $resourceClassIds) ? $termOrId : null)
            : (isset($resourceClassIds[$termOrId]) ? $resourceClassIds[$termOrId] : null);
    }

    /**
     * Get all resource classes by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getResourceClassIds()
    {
        if (isset($this->resourceClasses)) {
            return $this->resourceClasses;
        }

        $this->resourceClasses = [];
        $resourceClasses = $this->api()
            ->search('resource_classes', [], ['responseContent' => 'resource'])->getContent();
        foreach ($resourceClasses as $resourceClass) {
            $term = $resourceClass->getVocabulary()->getPrefix() . ':' . $resourceClass->getLocalName();
            $this->resourceClasses[$term] = $resourceClass->getId();
        }

        return $this->resourceClasses;
    }

    /**
     * Check if a string or a id is a resource template.
     *
     * @param string|int $labelOrId
     * @return bool
     */
    protected function isResourceTemplate($labelOrId)
    {
        return $this->getResourceTemplateId($labelOrId) !== null;
    }

    /**
     * Get a resource template by label or by id.
     *
     * @param string|int $labelOrId
     * @return int|null
     */
    protected function getResourceTemplateId($labelOrId)
    {
        $resourceTemplateIds = $this->getResourceTemplateIds();
        return is_numeric($labelOrId)
            ? (array_search($labelOrId, $resourceTemplateIds) ? $labelOrId : null)
            : (isset($resourceTemplateIds[$labelOrId]) ? $resourceTemplateIds[$labelOrId] : null);
    }

    /**
     * Get all resource templates by label.
     *
     * @return array Associative array of ids by label.
     */
    protected function getResourceTemplateIds()
    {
        if (isset($this->resourceTemplates)) {
            return $this->resourceTemplates;
        }

        $this->resourceTemplate = [];
        $resourceTemplates = $this->api()
            ->search('resource_templates', [], ['responseContent' => 'resource'])->getContent();
        foreach ($resourceTemplates as $resourceTemplate) {
            $this->resourceTemplates[$resourceTemplate->getLabel()] = $resourceTemplate->getId();
        }

        return $this->resourceTemplates;
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

                // Manage the property of a target when it is a resource type,
                // like "o:item_set {dcterms:title}".
                // It is used to set a metadata for derived resource (media for
                // item) or to find another resource (item set for item, as an
                // identifier name).
                if ($pos = strpos($target, '{')) {
                    $targetData = trim(substr($target, $pos + 1), '{} ');
                    $target = trim(substr($target, $pos));
                    $result['target'] = $target;
                    $result['target_data'] = $targetData;
                    $propertyId = $this->getPropertyId($targetData);
                    if ($propertyId) {
                        $result['target_data_value'] = [
                            'property_id' => $propertyId,
                            'type' => 'literal',
                            'is_public' => true,
                        ];
                    }
                } else {
                    $result['target'] = $target;
                }

                $propertyId = $this->getPropertyId($target);
                if ($propertyId) {
                    $result['value']['property_id'] = $propertyId;
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

    protected function prepareIdentifierName()
    {
        $this->identifierName = $this->getParam('identifier_name', ['o:id', 'dcterms:identifier']);
        if (empty($this->identifierName)) {
            $this->logger->warn(
                'No identifier name was selected.' // @translate
            );
            $this->identifierName = null;
            return;
        }

        // For quicker search, prepare the ids of the properties.
        $isSingle = !is_array($this->identifierName);
        if ($isSingle) {
            $this->identifierName = $this->getPropertyId($this->identifierName) ?: $this->identifierName;
            return;
        }

        foreach ($this->identifierName as $key => $idName) {
            $this->identifierName[$key] = $this->getPropertyId($idName) ?: $idName;
        }
        $this->identifierName = array_filter($this->identifierName);
        if (count($this->identifierName) === 1) {
            $this->identifierName = reset($this->identifierName);
        } elseif (empty($this->identifierName)) {
            $this->logger->err(
                'Invalid identifier names: check your params.' // @translate
            );
            $this->identifierName = null;
        }
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

    /**
     * Find a list of resource ids from a list of identifiers (or one id).
     *
     * When there are true duplicates and case insensitive duplicates, the first
     * case sensitive is returned, else the first case insensitive resource.
     *
     * @todo Manage Media source html.
     *
     * @uses\BulkImport\Mvc\Controller\Plugin\FindResourcesFromIdentifiers
     *
     * @param array|string $identifiers Identifiers should be unique. If a
     * string is sent, the result will be the resource.
     * @param string $resourceType The resource type if any.
     * @param string|int|array $identifierName Property as integer or term,
     * "o:id", a media ingester (url or file), or an associative array with
     * multiple conditions (for media source). May be a list of identifier
     * metadata names, in which case the identifiers are searched in a list of
     * properties and/or in internal ids.
     * @return array|int|null Associative array with the identifiers as key and the ids
     * or null as value. Order is kept, but duplicate identifiers are removed.
     * If $identifiers is a string, return directly the resource id, or null.
     */
    protected function findResourcesFromIdentifiers($identifiers, $resourceType = null, $identifierName = null)
    {
        if (!$this->findResourcesFromIdentifiers) {
            $this->findResourcesFromIdentifiers = $this->getServiceLocator()->get('ControllerPluginManager')
                // Use class name to use it even when CsvImport is installed.
                ->get(\BulkImport\Mvc\Controller\Plugin\FindResourcesFromIdentifiers::class);
        }

        $findResourcesFromIdentifiers = $this->findResourcesFromIdentifiers;
        $identifierName = $identifierName ?: $this->identifierName;
        return $findResourcesFromIdentifiers($identifiers, $identifierName, $resourceType);
    }

    /**
     * Find a resource id from a an identifier.
     *
     * @param string $identifier
     * @param string $resourceType The resource type if any.
     * @param string|int|array $identifierName Property as integer or term,
     * media ingester or "o:id", or an array with multiple conditions.
     * @return int|null
     */
    protected function findResourceFromIdentifier($identifier, $resourceType = null, $identifierName = null)
    {
        return $this->findResourcesFromIdentifiers($identifier, $resourceType, $identifierName);
    }
}
