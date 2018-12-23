<?php
namespace BulkImport\Form;

use BulkImport\Traits\ServiceLocatorAwareTrait;
use Omeka\Form\Element\ItemSetSelect;
use Omeka\Form\Element\ResourceClassSelect;
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
            'name' => 'o:resource_template',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Resource template', // @translate
                'empty_option' => 'Select a template…', // @translate
                'resource_value_options' => [
                    'resource' => 'resource_templates',
                    'query' => [],
                    'option_text_callback' => function ($resourceTemplate) {
                        return $resourceTemplate->label();
                    },
                ],
            ],
            'attributes' => [
                'id' => 'o-resource-template',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a template…', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'resource_templates']),
            ],
        ]);

        $this->add([
            'name' => 'o:resource_class',
            'type' => ResourceClassSelect::class,
            'options' => [
                'label' => 'Resource class', // @translate
                'empty_option' => 'Select a class…', // @translate
            ],
            'attributes' => [
                'id' => 'resource-class-select',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a class…', // @translate
            ],
        ]);

        $this->add([
            'name' => 'o:item_set',
            'type' => ItemSetSelect::class,
            'options' => [
                'label' => 'Item set', // @translate
                'empty_option' => 'Select item set…', // @translate
            ],
            'attributes' => [
                'id' => 'o-item-set',
                'class' => 'chosen-select',
                'multiple' => false,
                'required' => false,
                'data-placeholder' => 'Select item set…', // @translate
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:resource_template',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'o:resource_class',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'o:item_set',
            'required' => false,
        ]);
    }
}
