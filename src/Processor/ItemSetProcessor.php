<?php
namespace BulkImport\Processor;

use ArrayObject;
use BulkImport\Form\ItemSetProcessorConfigForm;
use BulkImport\Form\ItemSetProcessorParamsForm;
use Zend\Form\Form;

class ItemSetProcessor extends AbstractResourceProcessor
{
    protected $resourceType = 'item_sets';

    protected $resourceLabel = 'Item sets'; // @translate

    protected $configFormClass = ItemSetProcessorConfigForm::class;

    protected $paramsFormClass = ItemSetProcessorParamsForm::class;

    public function handleConfigForm(Form $form)
    {
        parent::handleConfigForm($form);

        $values = $form->getData();
        $config = $this->getConfig();
        if (isset($values['o:is_open'])) {
            $config['o:is_open'] = $values['o:is_open'];
        }
        $this->setConfig($config);
    }

    public function handleParamsForm(Form $form)
    {
        parent::handleParamsForm($form);

        $values = $form->getData();
        $params = $this->getParams();
        if (isset($values['o:is_open'])) {
            $params['o:is_open'] = $values['o:is_open'];
        }
        $this->setParams($params);
    }

    protected function baseResource()
    {
        $resource = parent::baseResource();
        $isOpen = $this->getParam('o:is_open', null);
        $resource['o:is_open'] = $isOpen;
        return $resource;
    }

    protected function processCellDefault(ArrayObject $resource, $target, array $values)
    {
        switch ($target) {
            case 'o:is_open':
                $value = array_pop($values);
                $resource['o:is_open'] = in_array(strtolower($value), ['false', 'no', 'off', 'closed'])
                    ? false
                    : (bool) $value;
                break;
            default:
                parent::processCellDefault($resource, $target, $values);
                break;
        }
    }
}
