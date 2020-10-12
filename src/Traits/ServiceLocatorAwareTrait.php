<?php declare(strict_types=1);

namespace BulkExport\Traits;

use Laminas\ServiceManager\ServiceLocatorInterface;

trait ServiceLocatorAwareTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Get the service locator.
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $services
     * @return self
     */
    public function setServiceLocator(ServiceLocatorInterface $services)
    {
        $this->serviceLocator = $services;
        return $this;
    }
}
