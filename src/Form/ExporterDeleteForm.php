<?php
namespace BulkExport\Form;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class ExporterDeleteForm extends Form
{
    use ServiceLocatorAwareTrait;

    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'exporter_submit',
            'type' => Fieldset::class,
        ]);

        $fieldset = $this->get('exporter_submit');

        $fieldset->add([
            'name' => 'submit',
            'type' => Element\Submit::class,
            'attributes' => [
                'id' => 'submitbutton',
                'value' => 'Delete exporter', // @translate
            ],
        ]);
        return $this;
    }
}
