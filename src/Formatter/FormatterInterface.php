<?php declare(strict_types=1);

namespace BulkExport\Formatter;

interface FormatterInterface
{
    /**
     * Get the name of the output format.
     */
    public function getLabel(): string;

    /**
     * Return the extension to use for the output filename.
     */
    public function getExtension(): string;

    /**
     * Specific headers for the response, generally including `Content-Type`.
     *
     * @return array
     */
    public function getResponseHeaders(): array;

    /**
     * Get the formatted resources.
     *
     * @return string|null|false False when error, else the result. If an output is
     * set, the content is directly written to it, so the content is null.
     */
    public function getContent();

    /**
     * Get the formatted resources as a response.
     */
    public function getResponse(): \Laminas\Http\PhpEnvironment\Response;

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
     * - limit (int): maximum number of resources to output. No limit if empty.
     * - site_slug (string): slug of a site for url of resources when needed.
     * @return self
     */
    public function format($resources, $output = null, array $options = []): FormatterInterface;
}
