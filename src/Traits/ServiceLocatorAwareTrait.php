<?php declare(strict_types=1);

namespace BulkExport\Traits;

use Laminas\ServiceManager\ServiceLocatorInterface;

trait ServiceLocatorAwareTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * Get the service locator.
     */
    public function getServiceLocator(): ServiceLocatorInterface
    {
        return $this->services;
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $services
     * @return self
     */
    public function setServiceLocator(ServiceLocatorInterface $services): self
    {
        $this->services = $services;
        return $this;
    }
}
