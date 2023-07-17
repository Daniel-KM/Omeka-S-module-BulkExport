<?php declare(strict_types=1);

namespace BulkExport\Traits;

trait ConfigurableTrait
{
    /**
     * @var array|\ArrayObject
     */
    protected $config = [];

    /**
     * @param array|\ArrayObject $config
     * @return self
     */
    public function setConfig($config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return array|\ArrayObject
     */
    public function getConfig()
    {
        return $this->config;
    }
}
