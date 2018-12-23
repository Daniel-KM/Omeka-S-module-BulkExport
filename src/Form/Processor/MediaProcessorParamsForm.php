<?php
namespace BulkImport\Form\Processor;

use BulkImport\Form\EntriesByBatchTrait;

class MediaProcessorParamsForm extends MediaProcessorConfigForm
{
    use EntriesByBatchTrait;

    public function init()
    {
        $this->baseFieldset();
        $this->addFieldsets();
        $this->addEntriesByBatch();
        $this->addMapping();

        $this->baseInputFilter();
        $this->addInputFilter();
        $this->addEntriesByBatchInputFilter();
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
                    'html' => 'Html', // @translate
                ],
            ],
        ]);
    }
}
