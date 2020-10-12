<?php declare(strict_types=1);

namespace BulkExport\Formatter;

class TemplateTxt extends AbstractViewFormatter
{
    protected $label = 'list';
    protected $extension = 'list.txt';
    protected $responseHeaders = [
        'Content-type' => 'text/plain',
    ];
}
