<?php
namespace BulkImport\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Fieldset;

class ItemsProcessorParamsForm extends ItemsProcessorConfigForm
{
    public function init()
    {
        parent::init();

        /** @var \BulkImport\Interfaces\Processor $processor */
        $processor = $this->getOption('processor');
        /** @var \BulkImport\Interfaces\Reader $reader */
        $reader = $processor->getReader();

        $this->add([
            'name' => 'mapping',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Mapping', // @translate
            ],
        ]);

        $fieldset = $this->get('mapping');

        // Add all columns from file as inputs.
        foreach ($reader->getAvailableFields() as $name) {
            $fieldset->add([
                'name' => $name,
                'type' => PropertySelect::class,
                'options' => [
                    'label' => $name,
                    'empty_option' => 'Select one or more property…', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                ],
            ]);
        }

        // Change required to false.
        foreach ($this->getInputFilter()->get('mapping')->getInputs() as $input) {
            $input->setRequired(false);
        }
    }
}
