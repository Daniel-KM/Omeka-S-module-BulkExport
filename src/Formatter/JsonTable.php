<?php declare(strict_types=1);

namespace BulkExport\Formatter;

class JsonTable extends AbstractFieldsJsonFormatter
{
    protected $label = 'json-table';
    protected $extension = 'table.json';
}
