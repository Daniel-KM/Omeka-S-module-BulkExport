<?php

namespace BulkExport\Form\Writer;

use Laminas\Form\Element;

class SpreadsheetWriterConfigForm extends FieldsWriterConfigForm
{
    public function init()
    {
        parent::init();

        return $this
            ->add([
                'name' => 'separator',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Multi-value separator', // @translate
                    'info' => 'To output all values of each property, cells can be multivalued with this separator.
It is recommended to use a character that is never used, like "|", or a random string.', // @translate
                ],
                'attributes' => [
                    'id' => 'separator',
                    'value' => '',
                ],
            ])
            ->appends()
            ->addInputFilters();
    }
}
