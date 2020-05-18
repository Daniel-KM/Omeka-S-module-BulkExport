<?php
namespace BulkExport\Formatter;

class Json extends AbstractFormatter
{
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
            return $this->processSingle();
        }

        // TODO Manage big json output (simply add "[" before and "]" after).
        if ($this->isId) {
            $list = [];
            // TODO Use the entityManager and expresion in()?
            foreach ($this->resourceIds as $resourceId) {
                try {
                    $list[] = $this->api->read($this->resourceType, ['id' => $resourceId])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                }
            }
        } elseif ($this->isQuery) {
            $list = $this->api->search($this->resourceType, $this->query)->getContent();
        } else {
            $list = &$this->resources;
        }

        $this->content = json_encode($list, $this->options['flags']);
        $this->toOutput();
    }

    protected function processSingle()
    {
        $this->content = json_encode($this->resource, $this->options['flags']);
        $this->toOutput();
    }
}
