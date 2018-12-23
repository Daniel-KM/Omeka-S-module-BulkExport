<?php
namespace BulkImport\Form\Processor;

class ItemSetProcessorParamsForm extends ItemSetProcessorConfigForm
{
    public function init()
    {
        $this->baseFieldset();
        $this->addFieldsets();
        $this->addMapping();

        $this->baseInputFilter();
        $this->addInputFilter();
        $this->addMappingFilter();
    }

    protected function prependMappingOptions()
    {
        $mapping = parent::prependMappingOptions();
        return array_merge_recursive($mapping, [
            'metadata' => [
                'label' => 'Resource metadata', // @translate
                'options' => [
                    'o:is_open' => 'Openness', // @translate
                ],
            ],
        ]);
    }
}
