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
     * Specific headers for the response, generally included `Content-Type`.
     *
     * @return array
     */
    public function getHeaders();

    /**
     * Get the formatted resources.
     *
     * @return string|null|false False when error, else the output itself. No
     * content is returned when the output is set.
     */
    public function getContent();

    /**
     * Prepare output for one or multiple resources or ids or via a query.
     *
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]|\Omeka\Api\Representation\AbstractResourceEntityRepresentation|int[]|int|array $resources
     * When it is a query, the option "resource_type" must be set and it cannot
     * be the generic "resources".
     * @param string|null $output May be a filepath. When set, the formatting is
     * directly processed.
     * @param array $options
     * @return self
     */
    public function format($resources, $output = null, array $options = []);
}
