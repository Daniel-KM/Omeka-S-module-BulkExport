<?php declare(strict_types=1);

namespace BulkExport\Form;

use Common\Form\Element as CommonElement;

class SiteSettingsFieldset extends SettingsFieldset
{
    public function init(): void
    {
        parent::init();

        $this
            ->get('bulkexport_limit')
            ->setOption('info', null);
    }

    protected function addDisplayViews(): self
    {
        return $this
            ->add([
                'name' => 'bulkexport_views',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Resource pages where to display exporters automatically', // @translate
                    'info' => 'The config should be compliant with module BlocksDisposition if you use it. When the theme supports resource page blocks, It is recommended to use them.', // @translate
                    'value_options' => [
                        'item_show' => 'Item / show', // @translate
                        'item_browse' => 'Item / browse', // @translate
                        'itemset_show' => 'Item set / show', // @translate
                        'itemset_browse' => 'Item set / browse', // @translate
                        'media_show' => 'Media / show', // @translate
                        'media_browse' => 'Media / browse', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_views',
                ],
            ]);
    }
}
