<?php
namespace BulkExport\Formatter;

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
        'separator' => ' | ',
    ];

    public function __construct()
    {
        $this->defaultOptions['enclosure'] = chr(0);
        $this->defaultOptions['escape'] = chr(0);
    }

    public function format($resources, $output = null, array $options = [])
    {
        // Force some options.
        $options = [
            'delimiter' => "\t",
            'enclosure' => chr(0),
            'escape' => chr(0),
        ] + $options;
        return parent::format($resources, $output, $options);
    }
}
