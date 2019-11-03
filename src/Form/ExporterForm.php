<?php
namespace BulkExport\Form;

use BulkExport\Entity\Exporter;
use BulkExport\Writer\Manager as WriterManager;
use BulkExport\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class ExporterForm extends Form
{
    use ServiceLocatorAwareTrait;

    /**
     * @var Exporter
     */
    protected $exporter;

    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'o:label',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Label', // @translate
            ],
            'attributes' => [
                'id' => 'o-label',
            ],
        ]);

        $this->add([
            'name' => 'o-module-bulk:writer_class',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Writer', // @translate
                'value_options' => $this->getWriterOptions(),
            ],
            'attributes' => [
                'id' => 'o-module-bulk-writer-class',
            ],
        ]);

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
                'value' => 'Save', // @translate
            ],
        ]);
    }

    public function setExporter(Exporter $exporter)
    {
        $this->exporter = $exporter;
    }

    protected function getWriterOptions()
    {
        $options = [];
        $writerManager = $this->getServiceLocator()->get(WriterManager::class);
        $writers = $writerManager->getPlugins();
        foreach ($writers as $key => $writer) {
            $options[$key] = $writer->getLabel();
        }
        return $options;
    }
}
