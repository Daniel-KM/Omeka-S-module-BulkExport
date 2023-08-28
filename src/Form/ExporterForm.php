<?php declare(strict_types=1);

namespace BulkExport\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;

class ExporterForm extends Form
{
    /**
     * @var array
     */
    protected $writerOptions = [];

    public function init()
    {
        parent::init();

        $this
            ->setAttribute('id', 'form-bulk-exporter')
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
                'name' => 'o-bulk:writer_class',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Writer', // @translate
                    'value_options' => $this->getWriterOptions(),
                ],
                'attributes' => [
                    'id' => 'o-bulk-writer-class',
                ],
            ]);

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

    public function setWriterOptions(array $writerOptions): self
    {
        $this->writerOptions = $writerOptions;
        return $this;
    }

    protected function getWriterOptions(): array
    {
        return $this->writerOptions;
    }
}
