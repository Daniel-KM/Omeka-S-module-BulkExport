<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Log\Logger;
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
    protected $totalError = 0;

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

        $multivalueSeparator = $this->reader->getParam('separator' , '');
        $hasMultivalueSeparator = $multivalueSeparator !== '';

        $insert = [];
        foreach ($this->reader as $index => $entry) {
            ++$this->totalIndexResources;
            $this->indexResource = $index + 1;
            $this->logger->log(Logger::NOTICE, sprintf('Processing resource index #%d', $this->indexResource)); // @translate

            $resource = clone $base;

            foreach ($mapping as $sourceField => $targets) {
                if (empty($targets)) {
                    continue;
                }
                if (!isset($entry[$sourceField])) {
                    continue;
                }

                $value = $entry[$sourceField];
                if ($hasMultivalueSeparator) {
                    $values = explode($multivalueSeparator, $value);
                    $values = array_map([$this, 'trimUnicode'], $values);
                } else {
                    $values = [$this->trimUnicode($value)];
                }
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
                ++$this->totalError;
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

        $this->logger->log(
            Logger::NOTICE,
            sprintf(
                'End of process: %d resources to process, %d processed, %d errors.', // @translate
                $this->totalIndexResources,
                $this->totalProcessed,
                $this->totalError
            )
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
            switch ($target) {
                case $this->fillGeneric($resource, $target, $values):
                    break;
                case $this->fillSpecific($resource, $target, $values):
                    break;
                default:
                    $resource[$target] = array_pop($values);
                    break;
            }
        }
    }

    protected function fillGeneric(ArrayObject $resource, $target, array $values)
    {
        switch ($target) {
            case 'o:resource_template':
                $value = array_pop($values);
                if ($value) {
                    $resource['o:resource_template'] = ['o:id' => $value];
                } else {
                    $resource['o:resource_template'] = null;
                }
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
            // Literal.
            case $this->isTerm($target):
                foreach ($values as $value) {
                    $resourceProperty = [
                        '@value' => $value,
                        'property_id' => $this->getProperty($target)->getId(),
                        'type' => 'literal',
                    ];
                    $resource[$target][] = $resourceProperty;
                }
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
            $resources = $this->api()
                ->batchCreate($resourceType, $data, [], ['continueOnError' => true])->getContent();
            foreach ($resources as $resource) {
                $this->logger->log(
                    Logger::NOTICE,
                    sprintf(
                        'Created %s #%d', // @translate
                        $this->getLabel(),
                        $resource->id()
                    )
                );
            }
        } catch (\Exception $e) {
            $this->logger->log(Logger::ERR, $e->__toString());
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
     * @return \Omeka\Api\Manager
     */
    protected function api()
    {
        if ($this->api) {
            return $this->api;
        }
        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        return $this->api;
    }
}
