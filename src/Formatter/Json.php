<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Json extends AbstractFormatter
{
    protected $maxDirectJsonEncode = 1000;

    protected $label = 'json';
    protected $extension = 'json';
    protected $mediaType = 'application/json';

    protected $defaultOptions = [
        'flags' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PARTIAL_OUTPUT_ON_ERROR,
    ];

    protected function process(): self
    {
        if ($this->isSingle) {
            $this->processSingle();
            return $this;
        }

        if ($this->isId && count($this->resourceIds) > $this->maxDirectJsonEncode) {
            $this->processByOne();
            return $this;
        }

        if ($this->isId) {
            // Process output one by one to avoid memory issue.
            $this->content = "[\n";
            foreach ($this->resourceIds as $resourceId) {
                try {
                    $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (NotFoundException $e) {
                    continue;
                }
                $this->content .= $this->getDataResource($resource);
                $this->content .= ",\n";
            }
            $this->content = rtrim($this->content, ",\n") . "\n]";
            $this->toOutput();
            return $this;
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
        return $this;
    }

    protected function processSingle(): self
    {
        $this->content = $this->getDataResource($this->resource);
        $this->toOutput();
        return $this;
    }

    protected function processByOne(): self
    {
        $this->initializeOutput();
        if ($this->hasError) {
            return $this;
        }

        fwrite($this->handle, "[\n");

        $revertedIndex = count($this->resourceIds);
        foreach ($this->resourceIds as $resourceId) {
            --$revertedIndex;
            try {
                $resource = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
            } catch (NotFoundException $e) {
                continue;
            }
            // TODO In the case the user asks something forbidden, there will be one trailing comma. See json-table.
            $append = $revertedIndex ? ',' : '';
            $jsonResource = $this->getDataResource($resource);
            fwrite($this->handle, $jsonResource. $append . "\n");
        }

        fwrite($this->handle, ']');

        $this->finalizeOutput();
        return $this;
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): string
    {
        return json_encode($resource, $this->options['flags']);
    }
}
