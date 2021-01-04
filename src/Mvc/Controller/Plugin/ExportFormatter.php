<?php declare(strict_types=1);

namespace BulkExport\Mvc\Controller\Plugin;

use BulkExport\Formatter\Manager as FormatterManager;
use Laminas\Http\PhpEnvironment\Response as HttpResponse;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class ExportFormatter extends AbstractPlugin
{
    /**
     * @var \BulkExport\Formatter\Manager
     */
    protected $formatterManager;

    public function __construct(FormatterManager $formatterManager)
    {
        $this->formatterManager = $formatterManager;
    }

    /**
     * Helper to export resource to a specific format.
     */
    public function __invoke(): self
    {
        return $this;
    }

    public function has(string $name): bool
    {
        return $this->formatterManager->has($name);
    }

    public function get(string $name): ?\BulkExport\Formatter\FormatterInterface
    {
        return $this->formatterManager->has($name)
            ? $this->formatterManager->get($name)
            : null;
    }

    /**
     * @param string $name
     * @param string $format
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]|\Omeka\Api\Representation\AbstractResourceEntityRepresentation|int[]|int $resources Can be one or multiple resources or ids.
     * @param array $options
     */
    public function format(string $format, $resources, array $options = []): ?\BulkExport\Formatter\FormatterInterface
    {
        if (!$this->has($format)) {
            return null;
        }
        return $this->formatterManager
            ->get($format)
            ->format($resources, $options);
    }

    /**
     * Format resources and export them via an http response.
     *
     * @param string $format
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]|\Omeka\Api\Representation\AbstractResourceEntityRepresentation|int[]|int $resources Can be one or multiple resources or ids.
     * @param string $resourceType
     * @param array $options
     */
    public function export($format, $resource, string $resourceType, array $options = []): ?HttpResponse
    {
        $format = $this->format();
        return $format
            ? $format->getResponse($resourceType)
            : null;
    }
}
