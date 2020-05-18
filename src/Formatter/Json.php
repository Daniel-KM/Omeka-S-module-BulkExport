<?php
namespace BulkExport\Formatter;

class Json extends AbstractFormatter
{
    protected $maxDirectJsonEncode = 1000;

    protected $label = 'json';
    protected $extension = 'json';
    protected $responseHeaders = [
        'Content-type' => 'application/json; charset=utf-8',
    ];
    protected $defaultOptions = [
        'flags' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PARTIAL_OUTPUT_ON_ERROR,
    ];

    protected function process()
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
            $this->resources = [];
            // TODO Use the entityManager and expression in()?
            foreach ($this->resourceIds as $resourceId) {
                try {
                    $this->resources[] = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    continue;
                }
            }
        }

        $this->content = json_encode($this->resources, $this->options['flags']);
        $this->toOutput();
    }

    protected function processSingle()
    {
        $this->content = json_encode($this->resource, $this->options['flags']);
        $this->toOutput();
    }

    protected function processByOne()
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
            fwrite($this->handle, json_encode($resource, $this->options['flags']) . $append . "\n");
        }

        fwrite($this->handle, ']');

        $this->finalizeOutput();
    }
}
