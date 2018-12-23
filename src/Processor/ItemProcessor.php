<?php
namespace BulkImport\Processor;

use BulkImport\Form\ItemProcessorConfigForm;
use BulkImport\Form\ItemProcessorParamsForm;
use Zend\Form\Form;

class ItemProcessor extends AbstractResourceProcessor
{
    protected $resourceType = 'items';

    protected $resourceLabel = 'Items'; // @translate

    protected $configFormClass = ItemProcessorConfigForm::class;

    protected $paramsFormClass = ItemProcessorParamsForm::class;

    public function handleConfigForm(Form $form)
    {
        parent::handleConfigForm($form);

        $values = $form->getData();
        $config = $this->getConfig();
        $config['o:item_set'] = $values['o:item_set'];
        $this->setConfig($config);
    }

    public function handleParamsForm(Form $form)
    {
        parent::handleParamsForm($form);

        $values = $form->getData();
        $params = $this->getParams();
        $params['o:item_set'] = $values['o:item_set'];
        $this->setParams($params);
    }

    protected function baseResource()
    {
        $resource = parent::baseResource();
        $itemSetIds = $this->getParam('o:item_set', []);
        foreach ($itemSetIds as $itemSetId) {
            $resource['o:item_set'][] = ['o:id' => $itemSetId];
        }
        $resource['o:media'] = [];
        return $resource;
    }
}
