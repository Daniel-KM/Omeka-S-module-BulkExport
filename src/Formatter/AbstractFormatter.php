<?php

namespace BulkExport\Formatter;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Log\Stdlib\PsrMessage;

abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var array
     */
    protected $responseHeaders = [];

    /**
     * @var array
     */
    protected $defaultOptions = [];

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Api
     */
    protected $api;

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    /**
     * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]
     */
    protected $resources = [];

    /**
     * @var int|]
     */
    protected $resourceIds = [];

    /**
     * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation
     */
    protected $resource = null;

    /**
     * @var array
     */
    protected $query = [];

    /**
     * @var string|null
     */
    protected $output = null;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * Note: The type "resources" can only be read by the api, not searched.
     *
     * @var string
     */
    protected $resourceType = null;

    /**
     * In formatter, it should be a single value, but for simplicity with
     * writer and traits, an array is available too. This is the json type.
     *
     * @var array
     */
    protected $resourceTypes;

    /**
     * @var bool
     */
    protected $isOutput = false;

    /**
     * @var bool
     */
    protected $isSingle = false;

    /**
     * @var bool
     */
    protected $isId = false;

    /**
     * @var bool
     */
    protected $isQuery = false;

    /**
     * @var bool
     */
    protected $hasError = false;

    /**
     * @var string|null
     */
    protected $content = null;

    /**
     * @var resource|null
     */
    protected $handle = null;

    public function setServiceLocator(ServiceLocatorInterface $services)
    {
        $this->services = $services;
        return $this;
    }

    protected function getServiceLocator()
    {
        return $this->services;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    public function getContent()
    {
        if ($this->hasError) {
            return false;
        }
        $this->process();
        if ($this->isOutput) {
            return null;
        }
        return $this->content;
    }

    public function format($resources, $output = null, array $options = [])
    {
        $this->reset();

        // The api is almost always required.
        $this->logger = $this->services->get('Omeka\Logger');
        $this->api = $this->services->get('ControllerPluginManager')->get('api');
        $this->translator = $this->getServiceLocator()->get('MvcTranslator');

        $resourceTypes = [
            'items',
            'item_sets',
            'media',
            'resources',
            'annotations',
        ];

        $options += [
            'resource_type' => null,
            'metadata' => [],
            'limit' => 0,
            'site_slug' => '',
            'is_admin_request' => false,
        ];
        $hasLimit = $options['limit'] > 0;

        if (!empty($options['resource_type']) && in_array($options['resource_type'], $resourceTypes)) {
            $this->resourceType = $options['resource_type'];
        }
        $options['resource_types'] = empty($this->resourceType)
            ? []
            : [$this->mapApiResourceToJsonResourceType($this->resourceType)];

        // Some quick checks to prepare params in all cases.
        if (!empty($resources)) {
            $this->isId = is_numeric($resources);
            $isResource = is_object($resources)
                && $resources instanceof \Omeka\Api\Representation\AbstractResourceEntityRepresentation;
            // Some formats manage a single or a list of resources differently.
            $this->isSingle = $this->isId || $isResource;
            if ($this->isSingle) {
                if ($this->isId) {
                    // With single id, fetch resource early for easier process.
                    try {
                        $this->resource = $this->api->read($this->resourceType ?: 'resources', ['id' => $resources])->getContent();
                        $this->isId = false;
                    } catch (\Omeka\Api\Exception\NotFoundException $e) {
                        $this->hasError = true;
                    }
                } else {
                    $this->resource = $resources;
                }
                if (!$this->hasError) {
                    $this->resourceType = $this->resource->resourceName();
                    // Simplify for formats that manage single/list the same.
                    $this->resources = [$this->resource];
                }
            } elseif (is_array($resources)) {
                $first = reset($resources);
                $isResource = is_object($first)
                    && $first instanceof \Omeka\Api\Representation\AbstractResourceEntityRepresentation;
                if ($isResource) {
                    $this->resources = $hasLimit ? array_slice($resources, 0, $options['limit']) : $resources;
                } else {
                    // This is a list of id if all keys are numeric.
                    $this->isId = count($resources) === count(array_filter($resources, 'is_numeric', ARRAY_FILTER_USE_KEY));
                    if ($this->isId) {
                        $this->resourceIds = array_values(array_unique(array_filter(array_map('intval', $resources))));
                        if ($hasLimit) {
                            $this->resourceIds = array_slice($this->resourceIds, 0, $options['limit']);
                        }
                    } else {
                        $this->isQuery = true;
                        $this->query = $resources;
                        $this->hasError = empty($this->resourceType) || $this->resourceType === 'resources';
                        if (!$this->hasError) {
                            // Most of the time, the query is processed by id
                            // for memory performance.
                            if ($hasLimit) {
                                $this->query['limit'] = (int) $options['limit'];
                            }
                            $this->resourceIds = $this->api->search($this->resourceType, $this->query, ['returnScalar' => 'id'])->getContent();
                            $this->isId = true;
                        }
                    }
                }
            } else {
                $this->hasError = true;
            }
        } elseif ($resources === false) {
            $this->hasError = true;
        }

        if (!$this->resourceType && !$this->hasError) {
            $this->resourceType = 'resources';
        }

        $this->output = $output;
        $this->isOutput = !empty($this->output);

        $this->options = $options + $this->defaultOptions;

        if ($this->hasError) {
            $this->content = false;
        }

        return $this;
    }

    protected function reset()
    {
        $this->resources = [];
        $this->resourceIds = [];
        $this->resource = null;
        $this->query = [];
        $this->output = null;
        $this->options = [];
        $this->resourceType = null;
        $this->isOutput = false;
        $this->isSingle = false;
        $this->isId = false;
        $this->isQuery = false;
        $this->hasError = false;
        $this->content = null;
        return $this;
    }

    /**
     * Save the content to the handle.
     */
    abstract protected function process();

    protected function initializeOutput()
    {
        $file = $this->isOutput ? $this->output : 'php://temp';

        $this->handle = fopen($file, 'w+');
        if (!$this->handle) {
            $this->hasError = true;
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'Unable to open output: {error}.', // @translate
                ['error' => error_get_last()['message']]
            ));
        }
        return $this;
    }

    protected function finalizeOutput()
    {
        if (!$this->handle) {
            $this->hasError = true;
            return $this;
        }
        if ($this->isOutput) {
            fclose($this->handle);
            return $this;
        }
        rewind($this->handle);
        $this->content = stream_get_contents($this->handle);
        fclose($this->handle);
        return $this;
    }

    /**
     * Write the content into output when it is not filled with the formatter.
     * The content is removed.
     */
    protected function toOutput()
    {
        if (!$this->isOutput || $this->hasError) {
            return $this;
        }

        $this->size = file_put_contents($this->output, $this->content);
        if ($this->size === false) {
            $this->hasError = true;
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'Unable to save output to file: {error}.', // @translate
                ['error' => error_get_last()['message']]
            ));
        }
        $this->content = null;
        return $this;
    }

    protected function mapApiResourceToJsonResourceType($resourceType)
    {
        $mapping = [
            // Core.
            'users' => 'o:User',
            'vocabularies' => 'o:Vocabulary',
            'resource_classes' => 'o:ResourceClass',
            'resource_templates' => 'o:ResourceTemplate',
            'properties' => 'o:Property',
            'items' => 'o:Item',
            'media' => 'o:Media',
            'item_sets' => 'o:ItemSet',
            'modules' => 'o:Module',
            'sites' => 'o:Site',
            'site_pages' => 'o:SitePage',
            'jobs' => 'o:Job',
            'resources' => 'o:Resource',
            'assets' => 'o:Asset',
            'api_resources' => 'o:ApiResource',
            // Modules.
            'annotations' => 'oa:Annotation',
        ];
        return isset($mapping[$resourceType]) ? $mapping[$resourceType] : null;
    }
}
