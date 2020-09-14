<?php

namespace BulkExport\Traits;

trait ParametrizableTrait
{
    /**
     * @var array|\ArrayObject
     */
    protected $params = [];

    /**
     * @param array|\ArrayObject $params
     * @return self
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return array|\ArrayObject
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getParam($name, $default = null)
    {
        if (array_key_exists($name, $this->params)) {
            return $this->params[$name];
        }
        return $default;
    }
}
