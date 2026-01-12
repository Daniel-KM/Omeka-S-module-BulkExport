<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use BulkExport\Traits\ServiceLocatorAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;

class Manager
{
    use ServiceLocatorAwareTrait;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $plugins;

    public function __construct(ServiceLocatorInterface $serviceLocator, array $config)
    {
        $this->setServiceLocator($serviceLocator);
        $this->config = $config;
    }

    public function getPlugins()
    {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $this->plugins = [];
        $services = $this->getServiceLocator();

        $factories = $this->config['factories'] ?? [];
        foreach ($factories as $name => $factory) {
            if (class_exists($name) && in_array(FormatterInterface::class, class_implements($name))) {
                $this->plugins[$name] = new $name($services);
            }
        }

        return $this->plugins;
    }

    public function has($name)
    {
        // Handle aliases
        $aliases = $this->config['aliases'] ?? [];
        if (isset($aliases[$name])) {
            $name = $aliases[$name];
        }

        $plugins = $this->getPlugins();
        return isset($plugins[$name]);
    }

    public function get($name)
    {
        // Handle aliases
        $aliases = $this->config['aliases'] ?? [];
        if (isset($aliases[$name])) {
            $name = $aliases[$name];
        }

        $plugins = $this->getPlugins();
        return $plugins[$name] ?? null;
    }
}
