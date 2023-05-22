<?php declare(strict_types=1);

namespace BulkExport\Form\Writer;

use Laminas\Form\Element;

class JsonTableWriterConfigForm extends FieldsWriterConfigForm
{
    public function init()
    {
        parent::init();

        return $this
            ->appends()
            ->addInputFilters();
    }
}
