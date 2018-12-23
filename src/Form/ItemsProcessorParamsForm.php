<?php
namespace Import\Form;

use Omeka\Form\Element\ResourceSelect;
use Zend\Form\Fieldset;

class ItemsProcessorParamsForm extends ItemsProcessorConfigForm
{
    public function init()
    {
        parent::init();

        /** @var \Import\Interfaces\Processor $processor */
        $processor = $this->getOption('processor');
        /** @var \Import\Interfaces\Reader $reader */
        $reader = $processor->getReader();

        $this->add([
            'name' => 'mapping',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Mapping', // Text label
            ],
        ]);

        // Add all columns from file as inputs.
        foreach ($reader->getAvailableFields() as $name) {
            $this->get('mapping')->add([
                'name' => $name,
                'type' => ResourceSelect::class,
                'options' => [
                    'label' => $name, // @translate
                    'empty_option' => 'Select Class', // @translate
                    'resource_value_options' => [
                        'resource' => 'properties',
                        'query' => [],
                        'option_text_callback' => function ($property) {
                            return [
                                $property->vocabulary()->label(),
                                $property->label(),
                            ];
                        },
                    ],
                ],
            ]);
        }

        // Change required to false.
        foreach ($this->getInputFilter()->get('mapping')->getInputs() as $input) {
            $input->setRequired(false);
        }
    }
}
