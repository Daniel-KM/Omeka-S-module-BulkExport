<?php
namespace BulkExport\Formatter;

use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * @var \Zend\ServiceManager\ServiceLocatorInterface
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
    protected $headers = [];

    /**
     * @var array
     */
    protected $defaultOptions = [];

    /**
     * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]|\Omeka\Api\Representation\AbstractResourceEntityRepresentation
     */
    protected $resources = [];

    /**
     * @var array
     */
    protected $query = [];

    /**
     * @var string|null
     */
    protected $output;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var bool
     */
    protected $isOutput;

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
     * Note: The type "resources" can only be read by the api, not searched.
     *
     * @var string
     */
    protected $resourceType;

    /**
     * @var bool
     */
    protected $hasError = false;

    /**
     * @var string|null
     */
    protected $content;

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

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getContent()
    {
        if ($this->hasError) {
            return false;
        }
        if ($this->isOutput) {
            return null;
        }
        $this->process();
        return $this->content;
    }

    public function format($resources, $output = null, array $options = [])
    {
        // Some quick checks to prepare params in all cases.
        if (empty($resources)) {
            $this->resources = [];
        } else {
            $this->isId = is_numeric($resources);
            $isResource = is_object($resources)
                && $resources instanceof \Omeka\Api\Representation\AbstractResourceEntityRepresentation;
            // Some formats manage a single or a list of resources differently.
            $this->isSingle = $this->isId || $isResource;
            if ($this->isSingle) {
                $this->resources = [$this->isId ? (int) $resources : $resources];
            } elseif (is_array($resources)) {
                $resource = reset($resources);
                $isResource = is_object($resource)
                    && $resource instanceof \Omeka\Api\Representation\AbstractResourceEntityRepresentation;
                if ($isResource) {
                    $this->resources = $resources;
                    $this->resourceType = $resource->resourceName();
                } else {
                    // This is a list of id if all keys are numeric.
                    $this->isId = count($resources) === count(array_filter($resources, 'is_numeric', ARRAY_FILTER_USE_KEY));
                    if ($this->isId) {
                        $this->resources = array_values(array_unique(array_filter(array_map('intval', $resources))));
                    } else {
                        $this->isQuery = true;
                        $this->query = $resources;
                    }
                }
            } else {
                $this->hasError = true;
            }
        }

        $resourceTypes = [
            'items',
            'item_sets',
            'media',
            'resources',
            'annotations',
        ];
        if (empty($this->resourceType)) {
            if (!empty($options['resource_type']) && in_array($options['resource_type'], $resourceTypes)) {
                $this->resourceType = $options['resource_type'];
            } elseif ($this->isQuery) {
                $this->hasError = true;
            } else {
                $this->resourceType = 'resources';
            }
        }

        $this->hasError = $this->hasError
            || ($this->isQuery && (empty($this->resourceType) || $this->resourceType === 'resources'));

        $this->output = $output;
        $this->isOutput = !empty($this->output);

        $this->options = $options + $this->defaultOptions;

        if ($this->hasError) {
            $this->content = false;
        } elseif ($this->isOutput) {
            $this->process();
        }

        return $this;
    }

    abstract protected function process();
}
