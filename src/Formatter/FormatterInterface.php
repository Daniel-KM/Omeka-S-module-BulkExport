<?php
namespace BulkExport\Formatter;

use Zend\ServiceManager\ServiceLocatorInterface;

interface FormatterInterface
{
    /**
     * @param ServiceLocatorInterface $serviceLocator
     * @return self
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator);

    /**
     * Get the name of the output format.
     *
     * @return string
     */
    public function getLabel();

    /**
     * Return the extension to use for the output filename.
     *
     * @return string
     */
    public function getExtension();

    /**
     * Specific headers for the response, generally including `Content-Type`.
     *
     * @return array
     */
    public function getResponseHeaders();

    /**
     * Get the formatted resources.
     *
     * @return string|null|false False when error, else the result. If an output is
     * set, the content is directly written to it, so the content is null.
     */
    public function getContent();

    /**
     * Prepare output for one or multiple resources or ids or via a query.
     *
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]|\Omeka\Api\Representation\AbstractResourceEntityRepresentation|int[]|int|array $resources
     * The types of the resources must not be mixed (int/object). When it is a
     * query, the option "resource_type" must be set and it cannot be the
     * generic type "resources".
     * @param string|null $output May be a filepath.
     * @param array $options Common options:
     * - resource_type (string): the type of the resources (items, item_sets…),
     * - limit (int): Maximum number of resources to output. No limit if empty.
     * @return self
     */
    public function format($resources, $output = null, array $options = []);
}
