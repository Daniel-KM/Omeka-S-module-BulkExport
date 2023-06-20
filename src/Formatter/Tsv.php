<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Laminas\ServiceManager\ServiceLocatorInterface;

class Tsv extends Csv
{
    protected $label = 'tsv';
    protected $extension = 'tsv';
    protected $responseHeaders = [
        'Content-type' => 'text/tab-separated-values',
    ];

    protected $defaultOptions = [
        'delimiter' => "\t",
        'enclosure' => 0,
        'escape' => 0,
    ];

    public function __construct(ServiceLocatorInterface $services)
    {
        parent::__construct($services);
        $this->defaultOptions['enclosure'] = chr(0);
        $this->defaultOptions['escape'] = chr(0);
    }
}
