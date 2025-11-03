<?php declare(strict_types=1);

namespace BulkExport\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class ShaperForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'bulk-shaper-form')

            ->add([
                'name' => 'o:label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'id' => 'o-label',
                ],
            ])

            ->add([
                'name' => 'o:config',
                'type' => ShaperConfigFieldset::class,
                'options' => [
                    'label' => 'Parameters', // @translate
                ],
            ])

            ->add([
                'name' => 'form_submit',
                'type' => Fieldset::class,
            ])
            ->get('form_submit')
            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'attributes' => [
                    'id' => 'submitbutton',
                    'value' => 'Save', // @translate
                ],
            ])
        ;
    }
}
