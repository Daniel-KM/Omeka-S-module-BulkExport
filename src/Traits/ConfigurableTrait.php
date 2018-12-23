<?php
namespace Import\Traits;

trait ConfigurableTrait
{
    protected $config = [];

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }
}
