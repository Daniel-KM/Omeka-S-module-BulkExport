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
            'item_sets' => [
                'label' => 'Item sets', // @translate
                'options' => [
                    'o:item_set' => 'Internal id', // @translate
                ],
            ],
            'media' => [
                'label' => 'Media', // @translate
                'options' => [
                    'url' => 'Url', // @translate
                    'file' => 'File', // @translate
                    'html' => 'Html', // @translate
                    'o:media {dcterms:title}' => 'Title', // @translate
                ],
            ],
        ]);
    }
}
