<?php
namespace Import\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ImportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'id' => $this->getId(),
            'reader_params' => $this->getReaderParams(),
            'processor_params' => $this->getProcessorParams(),
            'status' => $this->getStatus(),
            'started' => $this->getStarted(),
            'ended' => $this->getEnded(),
            'o:importer' => $this->getImporter(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o:importImport';
    }

    public function getResource() {
        return $this->resource;
    }

    /*
     * Magic getter to always pull data from resource
     */
    public function __call($method, $arguments)
    {
        if (substr($method, 0, 3) == 'get') {
            return $this->resource->$method();
        }
    }
}
