<?php
namespace Import\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class LogRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'o:id' => $this->getId(),
            'severity' => $this->getSeverity(),
            'message' => $this->getMessage(),
            'params' => $this->getParams(),
            'o:import' => $this->getImport(),
        ];
    }

    public function getJsonLdType()
    {
        return 'o-module-import:Log';
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
