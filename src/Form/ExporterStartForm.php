<?php
namespace BulkExport\Form;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class ExporterStartForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        $this->add([
            'name' => 'start_submit',
            'type' => Fieldset::class,
        ]);

        $fieldset = $this->get('start_submit');

        $fieldset->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'value' => 'Start export', // @translate
                'required' => true,
            ],
        ]);
        return $this;
    }
}
