<?php declare(strict_types=1);

namespace BulkExport\Formatter;

class JsonLd extends Json
{
    protected $label = 'json-ld';
    protected $responseHeaders = [
        'Content-type' => 'application/ld+json; charset=utf-8',
    ];
}
