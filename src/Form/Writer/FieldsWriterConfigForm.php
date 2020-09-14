<?php
namespace BulkExport\Form\Writer;

class FieldsWriterConfigForm extends AbstractWriterConfigForm
{
    use FormatTrait;
    use MetadataSelectTrait;
    use ResourceQueryTrait;
    use ResourceTypesSelectTrait;

    protected function appends()
    {
        return $this
            ->appendFormats()
            ->appendResourceTypesSelect()
            ->appendMetadataSelect()
            ->appendResourceQuery();
    }

    protected function addInputFilters()
    {
        return $this
            ->addInputFilterFormats()
            ->addInputFilterResourceTypes()
            ->addInputFilterMetadata();
    }
}
