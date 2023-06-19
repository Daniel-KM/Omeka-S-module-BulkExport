<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Omeka\Form\Element as OmekaElement;

trait ResourceQueryTrait
{
    public function appendResourceQuery()
    {
        $this
            ->add([
                'type' => OmekaElement\Query::class,
                'name' => 'query',
                'options' => [
                    'label' => 'Resource query', // @translate
                    'info' => 'Limit the resources output. Should be used with one resource type only.', // @translate
                    'documentation' => 'https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview',
                    'query_resource_type' => 'resources',
                    'query_partial_excludelist' => [
                    ],
                ],
                'attributes' => [
                    'id' => 'query',
                ],
            ])
        ;
        return $this;
    }
}
