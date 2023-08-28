<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

class FieldsWriterConfigForm extends AbstractWriterConfigForm
{
    use FormatTrait;
    use MetadataSelectTrait;
    use ResourceQueryTrait;
    use ResourceTypesSelectTrait;

    public function init()
    {
        $this
            ->appends()
            ->addInputFilters();
    }

    protected function appends(): self
    {
        return $this
            ->appendBase()
            ->appendFile()
            ->appendFormats()
            ->appendResourceTypesSelect()
            ->appendMetadataSelect()
            ->appendResourceQuery()
            ->appendIncremental()
            ->appendHistoryLogDeleted()
        ;
    }

    protected function addInputFilters(): self
    {
        return $this
            ->addInputFilterFormats()
            ->addInputFilterResourceTypes()
            ->addInputFilterMetadata()
        ;
    }
}
