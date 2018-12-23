<?php
namespace Import\Form;

use Import\Entity\Importer;
use Import\Reader\Manager as ReaderManager;
use Import\Processor\Manager as ProcessorManager;
use Import\Traits\ServiceLocatorAwareTrait;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class ImporterForm extends Form
{
    use ServiceLocatorAwareTrait;

    /**
     * @var Importer
     */
    protected $importer;

    public function init()
    {
        parent::init();

        $this->add([
            'name' => 'name',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
            ],
        ]);

        $this->add([
            'name' => 'reader_name',
            'type'  => Element\Select::class,
            'options' => [
                'label' => 'Reader', // @translate
                'value_options' => $this->getReaderOptions(),
            ],
            'attributes' => [
                'id' => 'reader_name',
            ],
        ]);

        $this->add([
            'name' => 'processor_name',
            'type'  => Element\Select::class,
            'options' => [
                'label' => 'Processor', // @translate
                'value_options' => $this->getProcessorOptions(),
            ],
            'attributes' => [
                'id' => 'processor_name',
            ],
        ]);

        $this->add([
            'name' => 'importer_submit',
            'type' => Fieldset::class,
        ]);

        $fieldset = $this->get('importer_submit');

        $fieldset->add([
            'type'  => Element\Submit::class,
            'name' => 'submit',
            'attributes' => [
                'id' => 'submitbutton',
                'value' => 'Save', // @translate
            ],
        ]);
    }

    public function setImporter(Importer $importer)
    {
        $this->importer = $importer;
    }

    protected function getReaderOptions()
    {
        $options = [];

        $readerManager = $this->getServiceLocator()->get(ReaderManager::class);
        $readers = $readerManager->getPlugins();

        foreach ($readers as $key => $reader) {
            $options[$key] = $reader->getLabel();
        }

        return $options;
    }

    protected function getProcessorOptions()
    {
        $options = [];

        $readerManager = $this->getServiceLocator()->get(ProcessorManager::class);
        $readers = $readerManager->getPlugins();

        foreach ($readers as $key => $reader) {
            $options[$key] = $reader->getLabel();
        }

        return $options;
    }
}
