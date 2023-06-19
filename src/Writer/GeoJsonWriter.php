<?php declare(strict_types=1);

namespace BulkExport\Writer;

class GeoJsonWriter extends AbstractFieldsJsonWriter
{
    protected $label = 'GeoJSON'; // @translate
    protected $extension = 'geojson';
    protected $mediaType = 'application/geo+json';
    protected $outputSingleAsMultiple = true;
    protected $outputIsObject = true;

    /**
     *
     * @var \BulkExport\Formatter\GeoJson
     */
    protected $geojsonFormatter;

    public function process(): WriterInterface
    {
        $this->options += $this->defaultOptions;

        $this->translator = $this->getServiceLocator()->get('MvcTranslator');

        // TODO Check if formatters params are the same.
        $this
            ->initializeParams()
            ->prepareTempFile();

        if ($this->hasError) {
            return $this;
        }

        $resourceIds = $this->getResourceIdsByType();
        $resourceIds = array_merge(...array_values($resourceIds));

        $this->geojsonFormatter = $this->services->get(\BulkExport\Formatter\Manager::class)->get('geojson');
        $this->geojsonFormatter
            ->format($resourceIds, $this->filepath, $this->options)
            ->getContent();

        $this
            ->saveFile();

        return $this;
    }
}
