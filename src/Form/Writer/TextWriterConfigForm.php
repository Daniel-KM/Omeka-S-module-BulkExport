<?php

namespace BulkExport\Form\Writer;

class TextWriterConfigForm extends FieldsWriterConfigForm
{
    public function init()
    {
        parent::init();

        return $this
            ->appends()
            ->addInputFilters();
    }
}
