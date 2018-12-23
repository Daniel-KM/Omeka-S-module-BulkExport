<?php
namespace BulkImport\Form;

class MediaProcessorParamsForm extends MediaProcessorConfigForm
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
            'item' => [
                'label' => 'Item', // @translate
                'options' => [
                    'o:item' => 'Internal id', // @translate
                ],
            ],
            'media' => [
                'label' => 'Media', // @translate
                'options' => [
                    'url' => 'Url', // @translate
                    'file' => 'File', // @translate
                ],
            ],
        ]);
    }
}
