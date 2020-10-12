<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractViewFormatter extends AbstractFormatter
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/bulk-export-resource';

    /**
     * Any view helper that returns a string from a resource.
     * If empty, the process will return the content of the view partial.
     *
     * @var string
     */
    protected $converterName;

    /**
     * @var \Laminas\View\Helper\AbstractHelper|callable;
     */
    protected $converter;

    protected function process(): void
    {
        $this->initializeOutput();
        if ($this->hasError) {
            return;
        }

        $viewHelpers = $this->services->get('ViewHelperManager');
        $isViewHelper = $viewHelpers->has($this->converterName);
        if ($isViewHelper) {
            $this->converter = $viewHelpers->get($this->converterName);
        } else {
            $view = $this->services->get('ViewRenderer');
            $template = empty($this->options['template']) || !$view->resolver($this->options['template'])
                ? self::PARTIAL_NAME
                : $this->options['template'];
            $partial = $viewHelpers->get('partial');
            $this->converter = function (AbstractResourceEntityRepresentation $resource, $index) use ($partial, $template) {
                return $partial($template, [
                    'resource' => $resource,
                    'index' => $index,
                ]);
            };
        }

        // The end user index is one-based.
        $index = 0;
        if ($this->isId) {
            foreach ($this->resourceIds as $resourceId) {
                try {
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    continue;
                }
                $this->writeResource($resource, ++$index);
            }
        } else {
            foreach ($this->resources as $resource) {
                $this->writeResource($resource, ++$index);
            }
        }

        $this->finalizeOutput();
    }

    protected function writeResource(AbstractResourceEntityRepresentation $resource, $index): void
    {
        $conv = $this->converter;
        fwrite($this->handle, $conv($resource, $index));
    }
}
