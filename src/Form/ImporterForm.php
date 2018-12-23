<?php
namespace Import\Form;

use Import\Entity\Importer;
use Import\Reader\Manager as ReaderManager;
use Import\Processor\Manager as ProcessorManager;

use Import\Traits\ServiceLocatorAwareTrait;
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
            'type' => 'text',
            'options' => [
                'label' => 'Name', // @translate
            ],
        ]);
        $this->add([
            'name' => 'reader_name',
            'type' => 'select',
            'options' => [
                'label' => 'Reader', // @translate
                'value_options' => $this->getReaderOptions(),
            ],
        ]);
        $this->add([
            'name' => 'processor_name',
            'type' => 'select',
            'options' => [
                'label' => 'Processor', // @translate
                'value_options' => $this->getProcessorOptions(),
            ],
        ]);

        $this->add([
            'name' => 'importer_submit',
            'type' => 'fieldset',
        ]);
        $this->get('importer_submit')->add([
            'type'  => 'submit',
            'name' => 'submit',
            'attributes' => [
                'value' => 'Save',
                'id' => 'submitbutton',
            ],
        ]);


    }

//    public function setImporter(Importer $importer)
//    {
//        $this->importer = $importer;
//    }

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
