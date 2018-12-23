<?php
namespace Import\Form;

use Import\Interfaces\Processor;
use Import\Interfaces\Reader;
use Omeka\Form\Element\ResourceSelect;


class ItemsProcessorParamsForm extends ItemsProcessorConfigForm
{
    public function init()
    {
        parent::init();

        /** @var  Processor $processor */
        $processor = $this->getOption('processor');
        /** @var Reader $reader */
        $reader = $processor->getReader();

        $this->add([
            'name' => 'mapping',
            'type' => 'fieldset',
            'options' => [
                'label' => 'Mapping',  // Text label
            ],
        ]);

        //add all columns from file as inputs
        foreach($reader->getAvailableFields() as $name) {
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

        //change required to false
        foreach($this->getInputFilter()->get('mapping')->getInputs() as $input) {
            $input->setRequired(false);
        }
    }
}
