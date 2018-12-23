<?php
namespace Import\Form;

use Import\Traits\ServiceLocatorAwareTrait;
use Omeka\Form\Element\ResourceSelect;
use Zend\Form\Form;

class ItemsProcessorConfigForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $serviceLocator = $this->getServiceLocator();
        $urlHelper = $serviceLocator->get('ViewHelperManager')->get('url');

        $this->add([
            'name' => 'o:item_set',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Item set', // @translate
                'info' => 'Select Item set', // @translate
                'resource_value_options' => [
                    'resource' => 'item_sets',
                    'query' => [],
                    'option_text_callback' => function ($itemSet) {
                        return $itemSet->displayTitle();
                    },
                ],
            ],
            'attributes' => [
                'id' => 'select-item-set',
                'required' => false,
                'multiple' => false,
                'data-placeholder' => 'Select item set', // @translate
            ],
        ]);

        $this->add([
            'name' => 'o:resource_template',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Resource template', // @translate
                'info' => 'A pre-defined template for resource creation', // @translate
                'empty_option' => 'Select Template', // @translate
                'resource_value_options' => [
                    'resource' => 'resource_templates',
                    'query' => [],
                    'option_text_callback' => function ($resourceTemplate) {
                        return $resourceTemplate->label();
                    },
                ],
            ],
            'attributes' => [
                'id' => 'resource-template-select',
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'resource_templates']),
            ],
        ]);

        $this->add([
            'name' => 'o:resource_class',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Class', // @translate
                'info' => 'A type for the resource. Different types have different default properties attached to them.', // @translate
                'empty_option' => 'Select Class', // @translate
                'resource_value_options' => [
                    'resource' => 'resource_classes',
                    'query' => [],
                    'option_text_callback' => function ($resourceClass) {
                        return [
                            $resourceClass->vocabulary()->label(),
                            $resourceClass->label(),
                        ];
                    },
                ],
            ],
            'attributes' => [
                'id' => 'resource-class-select',
            ],
        ]);
    }
}
