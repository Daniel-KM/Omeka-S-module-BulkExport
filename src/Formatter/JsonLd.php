<?php declare(strict_types=1);

namespace BulkExport\Formatter;

class JsonLd extends Json
{
    protected $label = 'json-ld';
    protected $extension = 'jsonld';
    protected $mediaType = 'application/ld+json; charset=utf-8';
}
