<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Json extends AbstractFormatter
{
    protected $maxDirectJsonEncode = 1000;

    protected $label = 'json';
    protected $extension = 'json';
    protected $responseHeaders = [
        'Content-type' => 'application/json',
    ];
    protected $defaultOptions = [
        'flags' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PARTIAL_OUTPUT_ON_ERROR,
    ];

    protected function process(): void
    {
        if ($this->isSingle) {
            $this->processSingle();
            return;
        }

        if ($this->isId && count($this->resourceIds) > $this->maxDirectJsonEncode) {
            $this->processByOne();
            return;
        }

        if ($this->isId) {
            // Process output one by one to avoid memory issue.
            $this->content = "[\n";
            foreach ($this->resourceIds as $resourceId) {
                try {
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    continue;
                }
                $this->content .= $this->getDataResource($resource);
                $this->content .= ",\n";
            }
            $this->content = rtrim($this->content, ",\n") . "\n]";
            $this->toOutput();
            return;
        }

        // Resources are already available.
        // Process output one by one to avoid memory issue.
        $this->content = "[\n";
        foreach ($this->resources as $resource) {
            $this->content .= $this->getDataResource($resource);
            $this->content .= ",\n";
        }
        $this->content = rtrim($this->content, ",\n") . "\n]";
        $this->toOutput();
    }

    protected function processSingle(): void
    {
        $this->content = $this->getDataResource($this->resource);
        $this->toOutput();
    }

    protected function processByOne(): void
    {
        $this->initializeOutput();
        if ($this->hasError) {
            return;
        }

        fwrite($this->handle, "[\n");

        $revertedIndex = count($this->resourceIds);
        foreach ($this->resourceIds as $resourceId) {
            --$revertedIndex;
            try {
                $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                continue;
            }
            // TODO In the case the user asks something forbidden, there will be one trailing comma.
            $append = $revertedIndex ? ',' : '';
            $jsonResource = $this->getDataResource($resource);
            fwrite($this->handle, $jsonResource. $append . "\n");
        }

        fwrite($this->handle, ']');

        $this->finalizeOutput();
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): string
    {
        return json_encode($resource, $this->options['flags']);
    }
}
