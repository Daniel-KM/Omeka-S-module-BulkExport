<?php declare(strict_types=1);

namespace BulkExport\Form;

use Common\Form\Element as CommonElement;

class SiteSettingsFieldset extends SettingsFieldset
{
    protected $elementGroups = [
        'export' => 'Bulk Export', // @translate
        'themes_old' => 'Old themes', // @translate
    ];

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
                'name' => 'bulkexport_placement',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'themes_old',
                    'label' => 'Bulk Export', // @translate
                    'value_options' => [
                        'after/items' => 'Item show', // @translate
                        'after/media' => 'Media show', // @translate
                        'after/item_sets' => 'Item set show', // @translate
                        'browse/items' => 'Item browse', // @translate
                        'browse/media' => 'Media browse', // @translate
                        'browse/item_sets' => 'Item set browse', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'bulkexport_placement',
                    'required' => false,
                ],
            ]);
    }
}
