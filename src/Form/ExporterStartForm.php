<?php declare(strict_types=1);

namespace BulkExport\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class ExporterStartForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'form-bulk-exporter')
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
                    'value' => 'Start export', // @translate
                    'required' => true,
                ],
            ]);
    }
}
