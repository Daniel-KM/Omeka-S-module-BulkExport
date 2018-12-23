<?php
namespace Import;

use Import\Module;
use Import\Traits\ServiceLocatorAwareTrait;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ResponseCollection;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractPluginManager
{
    use ServiceLocatorAwareTrait;

    /** @var EventManagerInterface */
    protected $eventManager;

    /** @var array */
    protected $plugins;

    abstract protected function getEventName();
    abstract protected function getInterface();

    /**
     * AbstractPluginManager constructor.
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $this->setEventManager($serviceLocator->get('EventManager'));
    }

    /**
     * @return EventManagerInterface
     */
    public function getEventManager() {
        return $this->eventManager;
    }

    /**
     * @param EventManagerInterface $eventManager
     * @return $this
     */
    public function setEventManager(EventManagerInterface $eventManager) {
        $this->eventManager = $eventManager;
        return $this;
    }

    /**
     * @return ResponseCollection
     */
    public function trigger()
    {
        $eventName = $this->getEventName();
        $eventManager = $this->getEventManager();

        $identifiers = $eventManager->getIdentifiers();

        $eventManager->addIdentifiers([Module::class]);
        $responseCollection = $eventManager->trigger($eventName, Module::class);

        $eventManager->setIdentifiers($identifiers);

        return $responseCollection;
    }

    public function getPlugins()
    {
        if ($this->plugins) return $this->plugins;

        $this->plugins = [];

        $responseCollection = $this->trigger();

        $items = [];
        foreach($responseCollection as $response) {
            $items = array_merge($items, $response);
        }

        $interface = $this->getInterface();
        foreach ($items as $name => $class) {
            if (class_exists($class) && in_array($interface, class_implements($class))) {
                $this->plugins[$name] = new $class($this->getServiceLocator());
            }
        }

        return $this->plugins;
    }

    public function getPlugin($name)
    {
        $plugins = $this->getPlugins();
        return isset($plugins[$name]) ? $plugins[$name] : null;
    }
}
