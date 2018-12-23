<?php
namespace Import\Processor;

use Import\Interfaces\Configurable;
use Import\Interfaces\Parametrizable;
use Import\Log\Logger;
use Import\Traits\ConfigurableTrait;
use Import\Traits\ParametrizableTrait;
use Import\Form\ItemsProcessorConfigForm;
use Import\Form\ItemsProcessorParamsForm;
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
            'o:item_set' => $values['o:item_set'],
            'o:resource_template' => $values['o:resource_template'],
            'o:resource_class' => $values['o:resource_class'],
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
            'mapping' => $values['mapping'],
            'o:item_set' => $values['o:item_set'],
            'o:resource_template' => $values['o:resource_template'],
            'o:resource_class' => $values['o:resource_class'],
        ];
        $this->setParams($params);
    }

    public function process()
    {
        $mapping = $this->getParam('mapping', []);
        $itemSetId = $this->getParam('o:item_set');
        $resourceTemplateId = $this->getParam('o:resource_template');
        $resourceClassId = $this->getParam('o:resource_class');

        $insert = [];
        foreach ($this->reader as $index => $entry) {
            $this->logger->log(Logger::NOTICE, sprintf('Processing row %s', $index + 1));

            $item = [
                'o:is_public' => true,
            ];
            if ($itemSetId) {
                $item['o:item_set'][] = ['o:id' => $itemSetId];
            }
            if ($resourceTemplateId) {
                $item['o:resource_template'] = ['o:id' => $resourceTemplateId];
            }
            if ($resourceClassId) {
                $item['o:resource_class'] = ['o:id' => $resourceClassId];
            }

            // $files = [];

            foreach ($mapping as $sourceField => $target) {
                if (empty($target)) {
                    continue;
                }
                if (isset($entry[$sourceField])) {
                    $value = $entry[$sourceField];

                    // Literal property.
                    if (is_numeric($target)) {
                        if (($property = $this->getProperty($target))) {
                            $itemProperty = [
                                '@value' => $value,
                                'property_id' => $property->getId(),
                                'type' => 'literal',
                            ];
                            $item[] = [$itemProperty];
                        }
                    } elseif (0 === strpos($target, 'file:')) {
                        // TODO Develop as a feature, as there are too many changes in media handling for refactoring.
                        // $strategy = substr($target, strpos($target, ':') + 1);
                        // $strategy = ucfirst($strategy);
                        // $files[] = [
                        //    'strategy' => $strategy,
                        //    'file' => $value,
                        // ];
                    } else {
                        $item[$target] = $value;
                    }
                }
            }

            $insert[] = $item;
            // Only add every X for batch import.
            if (($index + 1) % 20 == 0) {
                // Batch create.
                $this->createEntities($insert);
                $insert = [];
            }
        }
        // Take care of remainder from the modulo check.
        $this->createEntities($insert);
    }

    protected function createEntities($list)
    {
        try {
            $items = $this->getApi()
                ->batchCreate('items', $list, [], ['continueOnError' => true])->getContent();
            foreach ($items as $item) {
                $this->logger->log(Logger::NOTICE, sprintf('Created item %d', $item->id())); // @translate
            }
        } catch (\Exception $e) {
            $this->logger->log(Logger::ERR, $e->__toString());
        }
    }

    protected function getProperty($id)
    {
        $properties = $this->getProperties();
        return isset($properties[$id])
            ? $properties[$id]
            : null;
    }

    /**
     * @return array
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
            $this->properties[$property->getId()] = $property;
        }

        return $this->properties;
    }
}
