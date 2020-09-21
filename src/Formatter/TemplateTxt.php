<?php

namespace BulkExport\Formatter;

class TemplateTxt extends AbstractViewFormatter
{
    protected $label = 'list';
    protected $extension = 'list.txt';
    protected $responseHeaders = [
        'Content-type' => 'text/plain',
    ];
}
