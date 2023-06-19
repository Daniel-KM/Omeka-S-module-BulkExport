<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use DOMDocument;
use DOMXPath;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

/**
 *Output geojson for all specified resources as a whole
 *
 * The output is the same for one or multiple resources: a list of geojson data.
 *
 * @see https://www.geonames.org/ontology/documentation.html
 * @see https://www.geonames.org/ontology/ontology_v3.3.rdf
 * @see https://datatracker.ietf.org/doc/html/rfc7946
 */
class GeoJson extends AbstractFieldsJsonFormatter
{
    protected $label = 'geojson';
    protected $extension = 'geojson';
    protected $responseHeaders = [
        'Content-type' => 'application/geo+json',
    ];
    protected $outputSingleAsMultiple = true;
    protected $outputIsObject = true;
    protected $outputIsFull = true;

    /**
     * @var \Laminas\Http\Client $httpClient
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $httpHeadersQuery = [
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:115.0) Gecko/20100101 Firefox/115.0',
        'Content-Type' => 'application/json',
        'Accept-Encoding' => 'gzip, deflate',
    ];

    public function format($resources, $output = null, array $options = []): FormatterInterface
    {
        // Use omeka http client instead of the simple static client.
        $this->httpClient = $this->services->get('Omeka\HttpClient');
        return parent::format($resources, $output, $options + $this->defaultOptions);
    }

    protected function process(): void
    {
        $this
            ->prepareFieldNames($this->options['metadata'], $this->options['metadata_exclude']);

        if (!count($this->fieldNames)) {
            $this->logger->warn('No metadata are used in any resources.'); // @translate
            return;
        }

        $this->initializeOutput();
        if ($this->hasError) {
            return;
        }

        $geonamesUrlToResourceIds = [];

        if ($this->isId) {
            foreach ($this->resourceIds as $resourceId) {
                try {
                    /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (NotFoundException $e) {
                    continue;
                }
                $geonamesRdfUrls = $this->geonamesRdfUrls($resource);
                foreach ($geonamesRdfUrls as $geonamesRdfUrl) {
                    $geonamesUrlToResourceIds[$geonamesRdfUrl][] = $resourceId;
                }
            }
        } else {
            foreach ($this->resources as $resource) {
                $geonamesRdfUrls = $this->geonamesRdfUrls($resource);
                foreach ($geonamesRdfUrls as $geonamesRdfUrl) {
                    $geonamesUrlToResourceIds[$geonamesRdfUrl][] = $resource->Id();
                }
            }
        }

        $geoJsons = [];
        foreach ($geonamesUrlToResourceIds as $url => $resourceIds) {
            $geonamesRdf = $this->geonamesRdf($url);
            if ($geonamesRdf) {
                $geoJson = $this->geonamesRdfToGeoJson($geonamesRdf);
                if ($geoJson) {
                    $geoJson['properties']['o:id'] = $resourceIds;
                    $geoJsons[] = $geoJson;
                }
            }
        }

        // Start a geo json collection (list of features or shape data).
        fwrite($this->handle, '{' . "\n" . '"type": "FeatureCollection",' . "\n" . '"features": ');
        fwrite($this->handle, json_encode($geoJsons, $this->options['flags']));
        fwrite($this->handle, "\n" . '}');

        $this->finalizeOutput();
    }

    /**
     * Get geonames rdf uris in values of a resource.
     */
    protected function geonamesRdfUrls(AbstractResourceEntityRepresentation $resource): array
    {
        $result = [];
        $propertyTermIds = $this->getPropertiesByTerm();

        foreach ($this->fieldNames as $fieldName) {
            // Get only properties with a uri for data type "valuesuggest:geonames:geonames".
            if (!isset($propertyTermIds[$fieldName])) {
                continue;
            }

            $values = $resource->value($fieldName, ['all' => true, 'type' => 'valuesuggest:geonames:geonames']);
            if (!$values) {
                continue;
            }

            foreach ($values as $value) {
                $uri = (string) $value->uri();
                if (filter_var($uri, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                    $result[] = $uri;
                }
            }
        }

        $result = array_unique($result);
        foreach ($result as $key => $uri) {
            $result[$key] = $this->geonamesRdfUrl($uri);
        }
        $result = array_values(array_filter($result));

        return $result;
    }

    /**
     * Get the geonames rdf uri from the uri.
     */
    protected function geonamesRdfUrl(string $uri): ?string
    {
        $id = preg_replace('~.*/(?<id>[0-9]+).*~m', '$1', $uri);
        if (!$id) {
            $this->logger->err(
                'The geonames uri "{url}" is invalid.', // @translate
                ['url' => $uri]
            );
            return null;
        }
        return "https://sws.geonames.org/$id/about.rdf";
    }

    /**
     * @see https://www.geonames.org/ontology/documentation.html
     * @see https://www.geonames.org/ontology/ontology_v3.3.rdf
     * @see https://datatracker.ietf.org/doc/html/rfc7946
     */
    protected function geonamesRdfToGeoJson(DOMDocument $dom): array
    {
        // Allow to store parent countries names to avoid second level requests.
        static $countries = [];

        $language = $this->options['language'] ?? null;

        $xpaths = [
            'uri' => '/rdf:RDF/gn:Feature/@rdf:about',
            // 'altitude' => '',
            'latitude' => '/rdf:RDF/gn:Feature/wgs84_pos:lat[1]',
            'longitude' => '/rdf:RDF/gn:Feature/wgs84_pos:long[1]',
            // Region is generally the country or an island in geonames.
            'region' => '/rdf:RDF/gn:Feature/gn:parentCountry/@rdf:resource',
            'name' => [
                // If there is a language, use it first, else use name.
                $language ? '/rdf:RDF/gn:Feature/gn:officialName[@xml:lang="' . $language . '"][1]' : null,
                $language ? '/rdf:RDF/gn:Feature/gn:alternateName[@xml:lang="' . $language . '"][1]' : null,
                '/rdf:RDF/gn:Feature/gn:name[1]',
                '/rdf:RDF/gn:Feature/gn:shortName[1]',
                '/rdf:RDF/gn:Feature/gn:officialName[1]',
                '/rdf:RDF/gn:Feature/gn:alternateName[1]',
            ],
        ];

        $xpath = new DOMXPath($dom);

        $result = array_fill_keys(array_keys($xpaths), null);

        foreach ($xpaths as $key => $queries) {
            $isPlace = $key === 'name' || $key === 'region';
            if ($isPlace && array_key_exists($result['uri'], $countries)) {
                $result[$key] = $countries[$result['uri']];
                continue;
            }
            $value = $this->valueFromXPath($xpath, $queries);
            if ($value && $key === 'region') {
                $countries[$value] = null;
                // For the region/country, get the name instead of the uri.
                $regionDom = $this->geonamesRdf($value . 'about.rdf');
                if ($regionDom) {
                    $regionXpath = new DOMXPath($regionDom);
                    $countries[$value] = $this->valueFromXPath($regionXpath, $xpaths['name']);
                    $value = $countries[$value];
                }
            }
            $result[$key]  = $value;
        }

        $json = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                /** @see https://datatracker.ietf.org/doc/html/rfc7946#section-9 : In WGS84 longitude comes first. */
                'coordinates' => [(float) $result['longitude'], (float) $result['latitude']],
            ],
            'properties' => [
                'provenance' => [
                    'id' => $result['uri'],
                ],
                'region' => $result['region'],
                'name' => $result['name'],
            ],
        ];

        if (!$result['region']) {
            unset($json['properties']['region']);
        }

        return $json;
    }

    /**
     * Get the first value for a xpath.
     */
    protected function valueFromXPath(DOMXPath $xpath, $queries): ?string
    {
        foreach ((array) $queries as $query) {
            if ($query === null) {
                continue;
            }
            $nodeList = $xpath->query($query);
            if (!$nodeList || !$nodeList->length) {
                continue;
            }
            $value = trim((string) $nodeList->item(0)->nodeValue);
            if ($value === '') {
                continue;
            }
            return $value;
        }
        return null;
    }

    /**
     * Get geonames xml data from the rdf url.
     */
    protected function geonamesRdf(string $url): ?DOMDocument
    {
        $xml = $this->fetchUrl($url);
        if (!$xml) {
            return null;
        }

        // $simpleData = new SimpleXMLElement($xml, LIBXML_BIGLINES | LIBXML_COMPACT | LIBXML_NOBLANKS
        //     | /* LIBXML_NOCDATA | */ LIBXML_NOENT | LIBXML_PARSEHUGE);

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        try {
            $doc->loadXML($xml);
        } catch (\Exception $e) {
            $this->logger->err(
                'Output is not xml for url "{url}".', // @translate
                ['url' => $url]
            );
            return null;
        }

        if (!$doc) {
            $this->logger->err(
                'Output is not a valid xml for url "{url}".', // @translate
                ['url' => $url]
            );
            return null;
        }

        return $doc;
    }

    protected function fetchUrl($url): ?string
    {
        // TODO Should we reset cookies each time?
        $this->httpClient
            ->reset()
            ->setUri($url)
            ->setHeaders($this->httpHeadersQuery);

        try {
            // Remove deprecation warnings for old php code.
            $errorLevel = error_reporting();
            error_reporting(error_reporting() & ~E_DEPRECATED);
            $response = $this->httpClient->send();
            error_reporting($errorLevel);
        } catch (\Laminas\Http\Client\Exception\ExceptionInterface $e) {
            error_reporting($errorLevel);
            $this->logger->err(
                'Connection error when fetching url "{url}": {exception}', // @translate
                ['url' => $url, 'exception' => $e]
            );
            return null;
        }

        if (!$response->isSuccess()) {
            $this->logger->err(
                'Connection issue when fetching url "{url}": {msg}', // @translate
                ['url' => $url, 'msg' => $response->getReasonPhrase()]
            );
            return null;
        }

        $string = $response->getBody();
        if (!strlen($string)) {
            $this->logger->warn(
                'Output is empty for url "{url}".', // @translate
                ['url' => $url]
            );
        }

        return $string;
    }
}
