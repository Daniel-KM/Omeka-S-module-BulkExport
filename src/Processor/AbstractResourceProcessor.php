<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Form\ResourceProcessorConfigForm;
use BulkImport\Form\ResourceProcessorParamsForm;
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
    protected $resourceType = 'resources';

    /**
     * @var string
     */
    protected $resourceLabel = 'Resources'; // @translate

    /**
     * @var string
     */
    protected $configFormClass = ResourceProcessorConfigForm::class;

    /**
     * @var string
     */
    protected $paramsFormClass = ResourceProcessorParamsForm::class;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var array
     */
    protected $properties;

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
        $config = [];
        $config['o:resource_template'] = $values['o:resource_template'];
        $config['o:resource_class'] = $values['o:resource_class'];
        $config['o:is_public'] = $values['o:is_public'];
        $this->setConfig($config);
    }

    public function handleParamsForm(Form $form)
    {
        $values = $form->getData();
        $params = [];
        $params['o:resource_template'] = $values['o:resource_template'];
        $params['o:resource_class'] = $values['o:resource_class'];
        $params['o:is_public'] = $values['o:is_public'];
        $params['mapping'] = $values['mapping'];
        $this->setParams($params);
    }

    public function process()
    {
        $base = $this->baseResource();

        $mapping = $this->getParam('mapping', []);

        $multivalueSeparator = $this->reader->getParam('separator' , '');
        $hasMultivalueSeparator = $multivalueSeparator !== '';

        $insert = [];
        foreach ($this->reader as $index => $entry) {
            $this->logger->log(Logger::NOTICE, sprintf('Processing row %s', $index + 1)); // @translate

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

                $this->processCell($resource, $targets, $values);
            }

            $insert[] = $resource->getArrayCopy();
            // Only add every X for batch import.
            if (($index + 1) % self::BATCH == 0) {
                // Batch create.
                $this->createEntities($insert);
                $insert = [];
            }
        }
        // Take care of remainder from the modulo check.
        $this->createEntities($insert);
    }

    protected function baseResource()
    {
        $resource = new ArrayObject;
        $resourceTemplateId = $this->getParam('o:resource_template');
        if ($resourceTemplateId) {
            $resource['o:resource_template'] = ['o:id' => $resourceTemplateId];
        }
        $resourceClassId = $this->getParam('o:resource_class');
        if ($resourceClassId) {
            $resource['o:resource_class'] = ['o:id' => $resourceClassId];
        }
        $resource['o:is_public'] = $this->getParam('o:is_public') !== 'false';
        return $resource;
    }

    protected function processCell(ArrayObject $resource, array $targets, array $values)
    {
        foreach ($targets as $target) {
            switch ($target) {
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
                    break;
                case 'o:is_public':
                    $value = array_pop($values);
                    $resource['o:is_public'] = in_array(strtolower($value), ['false', 'no', 'off', 'private'])
                        ? false
                        : (bool) $value;
                    break;
                default:
                    $this->processCellDefault($resource, $target, $values);
                    break;
            }
        }
    }

    /**
     * Process one cell for a non-managed target.
     *
     * @param ArrayObject $resource
     * @param string $target
     * @param array $values
     */
    protected function processCellDefault(ArrayObject $resource, $target, array $values)
    {
        $resource[$target] = array_pop($values);
    }

    /**
     * Process creation of entities.
     *
     * @param array $data
     */
    protected function createEntities($data)
    {
        if (!count($data)) {
            return;
        }

        try {
            $resources = $this->api()
                ->batchCreate($this->getResourceType(), $data, [], ['continueOnError' => true])->getContent();
            foreach ($resources as $resource) {
                $this->logger->log(Logger::NOTICE, sprintf('Created %s #%d', $this->getLabel(), $resource->id())); // @translate
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
