<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Form\MediaProcessorConfigForm;
use BulkImport\Form\MediaProcessorParamsForm;
use BulkImport\Log\Logger;
use Zend\Form\Form;

class MediaProcessor extends AbstractResourceProcessor
{
    protected $resourceType = 'media';

    protected $resourceLabel = 'Media'; // @translate

    protected $configFormClass = MediaProcessorConfigForm::class;

    protected $paramsFormClass = MediaProcessorParamsForm::class;

    public function handleConfigForm(Form $form)
    {
        parent::handleConfigForm($form);

        $values = $form->getData();
        $config = $this->getConfig();
        $config['o:item'] = $values['o:item'];
        $this->setConfig($config);
    }

    public function handleParamsForm(Form $form)
    {
        parent::handleParamsForm($form);

        $values = $form->getData();
        $params = $this->getParams();
        $params['o:item'] = $values['o:item'];
        $this->setParams($params);
    }

    protected function baseResource()
    {
        $resource = parent::baseResource();
        $itemId = $this->getParam('o:item', null);
        $resource['o:item'] = ['o:id' => $itemId];
        return $resource;
    }

    protected function processCellDefault(ArrayObject $resource, $target, array $values)
    {
        switch ($target) {
            case 'o:item':
                $value = array_pop($values);
                $resource['o:item'] = ['o:id' => $value];
                break;
            case 'url':
                $value = array_pop($values);
                $resource['o:ingester'] = 'url';
                $resource['ingest_url'] = $value;
                break;
            case 'sideload':
                $value = array_pop($values);
                $resource['o:ingester'] = 'sideload';
                $resource['ingest_filename'] = $value;
                break;
            default:
                parent::processCellDefault($resource, $target, $values);
                break;
        }
    }

    protected function checkEntity(ArrayObject $resource)
    {
        if (empty($resource['o:item']['o:id'])) {
            $this->logger->log(Logger::ERROR, sprintf('Skipped media row %s: no item is set.', $this->indexRow)); // @translate
            return false;
        }
        return true;
    }
}
