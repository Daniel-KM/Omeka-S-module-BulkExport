<?php
namespace BulkImport\Traits;

trait ParametrizableTrait
{
    /**
     * @var array
     */
    protected $params;

    public function setParams($params)
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getParam($name, $default = null)
    {
        if (isset($this->params) && array_key_exists($name, $this->params)) {
            return $this->params[$name];
        }

        return $default;
    }
}
