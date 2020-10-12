<?php
namespace BulkExport\Form\Writer;

use Laminas\Form\Element;

trait ResourceQueryTrait
{
    public function appendResourceQuery()
    {
        $this->add([
            'name' => 'query',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Resource query', // @translate
                'info' => 'Limit the resources output. Should be used with one resource type only.', // @translate
                'documentation' => 'https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview',
            ],
            'attributes' => [
                'id' => 'query',
            ],
        ]);
        return $this;
    }
}
