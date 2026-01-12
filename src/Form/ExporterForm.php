<?php declare(strict_types=1);

namespace BulkExport\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class ExporterForm extends Form
{
    /**
     * @var array
     */
    protected $formatterOptions = [];

    public function init(): void
    {
        parent::init();

        $this
            ->setAttribute('id', 'bulk-exporter-form')
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
                'name' => 'o-bulk:formatter',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Format', // @translate
                    'value_options' => $this->getFormatterOptions(),
                ],
                'attributes' => [
                    'id' => 'o-bulk-formatter',
                ],
            ])

            ->add([
                'name' => 'o:config',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Other params', // @translate
                ],
            ]);

        $fieldset = $this->get('o:config');
        $fieldset
            ->add([
                'name' => 'exporter',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Exporter', // @translate
                ],
            ]);
        $subFieldset = $fieldset->get('exporter');
        $subFieldset
            ->add([
                'name' => 'as_task',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Store exporter as a task', // @translate
                    'info' => 'Allows to store a job to run it via command line or a cron task (see module EasyAdmin).', // @translate
                ],
                'attributes' => [
                    'id' => 'as_task',
                ],
            ])
            ->add([
                'name' => 'notify_end',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Notify by email when finished', // @translate
                ],
                'attributes' => [
                    'id' => 'notify_end',
                ],
            ])
        ;

        $this
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
                    'value' => 'Save', // @translate
                ],
            ]);
    }

    public function setFormatterOptions(array $formatterOptions): self
    {
        $this->formatterOptions = $formatterOptions;
        return $this;
    }

    protected function getFormatterOptions(): array
    {
        return $this->formatterOptions;
    }
}
