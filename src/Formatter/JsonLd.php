<?php declare(strict_types=1);

namespace BulkExport\Formatter;

class JsonLd extends Json
{
    protected $label = 'json-ld';
    protected $mediaType = 'application/ld+json; charset=utf-8';
}
