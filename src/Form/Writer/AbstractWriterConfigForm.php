<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Form;

abstract class AbstractWriterConfigForm extends Form
{
    protected function appendBase(): self
    {
        $this
            ->setAttribute('id', 'bulk-exporter-form')
            ->add([
                'name' => 'comment',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Comment', // @translate
                    'info' => 'This optional comment will help admins for future reference.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment',
                    'value' => '',
                    'placeholder' => 'Optional comment for future reference.', // @translate
                ],
            ])
        ;
        return $this;
    }

    protected function appendHistoryLogDeleted(): self
    {
        if (!class_exists('HistoryLog\Module', false)) {
            return $this;
        }

        $this
            ->add([
                'name' => 'include_deleted',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Include deleted resources according to date (require module HistoryLog, query unsupported)', // @translate
                    'value_options' => [
                        '' => 'No', // @translate
                        'id' => 'Id only', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'include_deleted',
                    'value' => '',
                ],
            ])
        ;
        return $this;
    }

    protected function appendFile(): self
    {
        $this
            ->add([
                'name' => 'dirpath',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Directory', // @translate
                    'info' => 'This setting allows to store the file in the specified location. It should be writeable by the web server. It may be relative to Omeka root. Available placeholders are: "{label}", "{exporter}", "{export_id}", "{date}", "{time}", "{user_id}", "{username}", "{random}".', // @translate
                ],
                'attributes' => [
                    'id' => 'dirpath',
                    'placeholder' => 'files/bulk_export/{date}',
                ],
            ])
            // Don't use "file" or "filename" because the name is already used.
            ->add([
                'name' => 'filebase',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Filename', // @translate
                    'info' => 'This setting allows to store the file always with the same name, generally for server tasks. The existing file will be overridden. Available placeholders are: "{label}", "{exporter}", "{export_id}", "{date}", "{time}", "{user_id}", "{username}", "{random}".', // @translate
                ],
                'attributes' => [
                    'id' => 'filebase',
                    'placeholder' => '{label]-{date}-{time}',
                ],
            ])
        ;
        return $this;
    }

    protected function appendMore(): self
    {
        $this
            ->add([
                'name' => 'zip_files',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Export all original or thumbnails as zip', // @translate
                    'value_options' => [
                        'original' => 'Original', // @translate
                        'large' => 'Large', // @translate
                        'medium' => 'Medium', // @translate
                        'square' => 'Square', // @translate
                        'asset' => 'Attached asset', // @translate
                        'asset_original' => 'Asset, else original', // @translate
                        'asset_large' => 'Asset, else large', // @translate
                        'asset_medium' => 'Asset, else medium', // @translate
                        'asset_square' => 'Asset, else square', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'zip_files',
                ],
            ])
            ->add([
                'name' => 'incremental',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Incremental since last successful export', // @translate
                    'info' => 'Only new and updated resources since last completed export with the same exporter and user will be output.', // @translate
                ],
                'attributes' => [
                    'id' => 'incremental',
                    'checked' => false,
                ],
            ])
        ;
        return $this;
    }

    protected function appendLast(): self
    {
        $this
            ->add([
                'name' => 'use_background',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Use a background job', // @translate
                    'info' => 'For complex formats or numerous resources, the process may require more than 30 seconds, that is the default duration of web server before error.', // @translate
                ],
                'attributes' => [
                    'id' => 'use_background',
                    'checked' => true,
                ],
            ])
        ;
        return $this;
    }
}
