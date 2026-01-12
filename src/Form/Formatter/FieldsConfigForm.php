<?php declare(strict_types=1);

namespace BulkExport\Form\Formatter;

class FieldsConfigForm extends AbstractConfigForm
{
    use FormatTrait;
    use MetadataSelectTrait;
    use ResourceQueryTrait;
    use ResourceTypesSelectTrait;

    public function init(): void
    {
        $this
            ->setAttribute('id', 'bulk-importer-form')

            ->appendBase()
            ->appendResourceTypesSelect()
            ->appendResourceQuery()
            ->appendMetadataSelect()
            ->appendHistoryLogDeleted()
            ->appendFormats()
            ->appendSpecific()
            ->appendFile()
            ->appendMore()
            ->appendLast()
        ;
     }

     protected function appendSpecific(): self
     {
        return $this;
    }
}
