<?php declare(strict_types=1);

namespace BulkExport\Writer;

class JsonTableWriter extends AbstractFieldsJsonWriter
{
    protected $label = 'Json Table'; // @translate
    protected $extension = 'table.json';
}
