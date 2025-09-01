<?php declare(strict_types=1);

namespace BulkExport\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class ExporterDeleteForm extends Form
{
    public function init(): void
    {
        parent::init();

        $this
            ->setAttribute('id', 'bulk-exporter-form')
            ->add([
                    'name' => 'form_submit',
                    'type' => Fieldset::class,
                ]);

        $fieldset = $this->get('form_submit');
        $fieldset
            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'attributes' => [
                    'id' => 'submitbutton',
                    'value' => 'Delete exporter', // @translate
                ],
            ]);
    }
}
