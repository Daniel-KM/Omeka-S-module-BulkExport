<?php
namespace BulkImport\Form;

use BulkImport\Traits\ServiceLocatorAwareTrait;
use Omeka\Form\Element\PropertySelect;
use Omeka\Form\Element\ResourceClassSelect;
use Omeka\Form\Element\ResourceSelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

abstract class AbstractResourceProcessorConfigForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        $this->baseFieldset();
        $this->addFieldsets();

        $this->baseInputFilter();
        $this->addInputFilter();
    }

    protected function baseFieldset()
    {
        $services = $this->getServiceLocator();
        $urlHelper = $services->get('ViewHelperManager')->get('url');

        $this->add([
            'name' => 'o:resource_template',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Resource template', // @translate
                'empty_option' => '',
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
                'empty_option' => '',
                'term_as_value' => true,
            ],
            'attributes' => [
                'id' => 'resource-class-select',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a class…', // @translate
            ],
        ]);

        $this->add([
            'name' => 'o:is_public',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Visibility', // @translate
                'value_options' => [
                    'true' => 'Public', // @translate
                    'false' => 'Private', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'o-is-public',
            ],
        ]);
    }

    protected function addFieldsets()
    {
    }

    protected function addMapping()
    {
        /** @var \BulkImport\Interfaces\Processor $processor */
        $processor = $this->getOption('processor');
        /** @var \BulkImport\Interfaces\Reader $reader */
        $reader = $processor->getReader();

        $services = $this->getServiceLocator();
        $automapFields = $services->get('ViewHelperManager')->get('automapFields');

        $this->add([
            'name' => 'mapping',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Mapping', // @translate
            ],
        ]);

        $fieldset = $this->get('mapping');

        // Add all columns from file as inputs.
        $availableFields = $reader->getAvailableFields();
        $fields = $automapFields($availableFields);
        foreach ($availableFields as $index => $name) {
            $fieldset->add([
                'name' => $name,
                'type' => PropertySelect::class,
                'options' => [
                    'label' => $name,
                    'term_as_value' => true,
                    'prepend_value_options' => $this->prependMappingOptions(),
                ],
                'attributes' => [
                    'value' => isset($fields[$index]) ? $fields[$index] : null,
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select one or more targets…', // @translate
                ],
            ]);
        }
    }

    protected function prependMappingOptions()
    {
        return [
            'metadata' => [
                'label' => 'Resource metadata', // @translate
                'options' => [
                    'o:resource_template' => 'Resource template id', // @translate
                    'o:resource_class' => 'Resource class', // @translate
                    'o:is_public' => 'Visibility public/private', // @translate
                ],
            ],
        ];
    }

    protected function baseInputFilter()
    {
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
            'name' => 'o:is_public',
            'required' => false,
        ]);
    }

    protected function addInputFilter()
    {
    }

    protected function addMappingFilter()
    {
        $inputFilter = $this->getInputFilter()->get('mapping');
        // Change required to false.
        foreach ($inputFilter->getInputs() as $input) {
            $input->setRequired(false);
        }
    }
}
