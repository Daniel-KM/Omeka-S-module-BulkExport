<?php
namespace BulkImport\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Fieldset;

class ItemsProcessorParamsForm extends ItemsProcessorConfigForm
{
    public function init()
    {
        $this->hasInputFilter = false;
        parent::init();
        $this->hasInputFilter = true;

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
                    'empty_option' => 'Select one or more properties…', // @translate
                    'term_as_value' => true,
                    'prepend_value_options' => [
                        'https:' => 'Url', // @translate
                        'file:' => 'File (via sideload)', // @translate
                    ],
                ],
                'attributes' => [
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select one or more properties…', // @translate
                ],
            ]);
        }

        $this->updateInputFilters();
    }

    protected function updateInputFilters()
    {
        parent::updateInputFilters();

        // Change required to false.
        foreach ($this->getInputFilter()->get('mapping')->getInputs() as $input) {
            $input->setRequired(false);
        }
    }
}
