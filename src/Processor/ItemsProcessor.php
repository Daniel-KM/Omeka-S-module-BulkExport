<?php
namespace BulkImport\Processor;

use BulkImport\Interfaces\Configurable;
use BulkImport\Interfaces\Parametrizable;
use BulkImport\Log\Logger;
use BulkImport\Traits\ConfigurableTrait;
use BulkImport\Traits\ParametrizableTrait;
use BulkImport\Form\ItemsProcessorConfigForm;
use BulkImport\Form\ItemsProcessorParamsForm;
use Zend\Form\Form;

class ItemsProcessor extends AbstractProcessor implements Configurable, Parametrizable
{
    use ConfigurableTrait, ParametrizableTrait;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var array
     */
    protected $properties;

    public function getLabel()
    {
        return 'Items'; // @translate
    }

    public function getConfigFormClass()
    {
        return ItemsProcessorConfigForm::class;
    }

    public function handleConfigForm(Form $form)
    {
        $values = $form->getData();

        $config = [
            'o:resource_template' => $values['o:resource_template'],
            'o:resource_class' => $values['o:resource_class'],
            'o:item_set' => $values['o:item_set'],
        ];

        $this->setConfig($config);
    }

    public function getParamsFormClass()
    {
        return ItemsProcessorParamsForm::class;
    }

    public function handleParamsForm(Form $form)
    {
        $values = $form->getData();
        $params = [
            'o:resource_template' => $values['o:resource_template'],
            'o:resource_class' => $values['o:resource_class'],
            'o:item_set' => $values['o:item_set'],
            'mapping' => $values['mapping'],
        ];
        $this->setParams($params);
    }

    public function process()
    {
        $base = [];
        $base['o:is_public'] = true;
        $resourceTemplateId = $this->getParam('o:resource_template');
        if ($resourceTemplateId) {
            $base['o:resource_template'] = ['o:id' => $resourceTemplateId];
        }
        $resourceClassId = $this->getParam('o:resource_class');
        if ($resourceClassId) {
            $base['o:resource_class'] = ['o:id' => $resourceClassId];
        }
        $itemSetIds = $this->getParam('o:item_set', []);
        foreach ($itemSetIds as $itemSetId) {
            $base['o:item_set'][] = ['o:id' => $itemSetId];
        }
        $base['o:media'] = [];

        $mapping = $this->getParam('mapping', []);

        $insert = [];
        foreach ($this->reader as $index => $entry) {
            $this->logger->log(Logger::NOTICE, sprintf('Processing row %s', $index + 1)); // @translate

            $item = $base;

            foreach ($mapping as $sourceField => $targets) {
                if (empty($targets)) {
                    continue;
                }
                if (!isset($entry[$sourceField])) {
                    continue;
                }
                $value = $entry[$sourceField];
                foreach ($targets as $target) {
                    // TODO Develop file load as a feature, as there are too many changes in media handling for refactoring.
                    // Literal property.
                    $property = $this->getProperty($target);
                    if ($property) {
                        $itemProperty = [
                            '@value' => $value,
                            'property_id' => $property->getId(),
                            'type' => 'literal',
                        ];
                        $item[] = [$itemProperty];
                    } elseif (0 === strpos($target, 'https:') || 0 === strpos($target, 'http:')) {
                        $file = [];
                        $file['o:is_public'] = true;
                        $file['o:ingester'] = 'url';
                        $file['ingest_url'] = $value;
                        $item['o:media'][] = $file;
                    } elseif (0 === strpos($target, 'file:')) {
                        $file = [];
                        $file['o:is_public'] = true;
                        $file['o:ingester'] = 'sideload';
                        $file['ingest_filename'] = $value;
                        $item['o:media'][] = $file;
                    } else {
                        $item[$target] = $value;
                    }
                }
            }

            $insert[] = $item;
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

    /**
     * Process creation of entities.
     *
     * @param array $data
     */
    protected function createEntities($data)
    {
        if (empty($data)) {
            return;
        }

        try {
            $items = $this->getApi()
                ->batchCreate('items', $data, [], ['continueOnError' => true])->getContent();
            foreach ($items as $item) {
                $this->logger->log(Logger::NOTICE, sprintf('Created item %d', $item->id())); // @translate
            }
        } catch (\Exception $e) {
            $this->logger->log(Logger::ERR, $e->__toString());
        }
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
        $resourceClasses = $this->getApi()
            ->search('resource_classes', [], ['responseContent' => 'resource'])->getContent();
        foreach ($resourceClasses as $resourceClass) {
            $term = $resourceClass->getVocabulary()->getPrefix() . ':' . $resourceClass->getLocalName();
            $this->resourceClasses[$term] = $resourceClass;
        }

        return $this->resourceClasses;
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
        $properties = $this->getApi()
            ->search('properties', [], ['responseContent' => 'resource'])->getContent();
        foreach ($properties as $property) {
            $term = $property->getVocabulary()->getPrefix() . ':' . $property->getLocalName();
            $this->properties[$term] = $property;
        }

        return $this->properties;
    }

    /**
     * @return \Omeka\Api\Manager
     */
    protected function getApi()
    {
        if ($this->api) {
            return $this->api;
        }
        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        return $this->api;
    }
}
