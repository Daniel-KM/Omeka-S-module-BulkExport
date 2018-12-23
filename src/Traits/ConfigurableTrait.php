<?php
namespace BulkImport\Traits;

trait ConfigurableTrait
{
    /**
     * @var array
     */
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
