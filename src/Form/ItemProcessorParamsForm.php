<?php
namespace BulkImport\Form;

class ItemProcessorParamsForm extends ItemProcessorConfigForm
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
            'media' => [
                'label' => 'Media', // @translate
                'options' => [
                    'url' => 'Url', // @translate
                    'sideload' => 'File (via sideload)', // @translate
                ],
            ],
        ]);
    }
}
