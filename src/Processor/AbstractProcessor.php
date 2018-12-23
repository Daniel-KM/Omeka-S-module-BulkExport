<?php
namespace BulkImport\Processor;

use BulkImport\Interfaces\Processor;
use BulkImport\Interfaces\Reader;
use BulkImport\Traits\ServiceLocatorAwareTrait;
use Zend\Log\Logger;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractProcessor implements Processor
{
    use ServiceLocatorAwareTrait;

    /**
     * Default limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const BATCH = 20;

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Processor constructor.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
    }

    public function setReader(Reader $reader)
    {
        $this->reader = $reader;
        return $this;
    }

    /**
     * @return \BulkImport\Interfaces\Reader
     */
    public function getReader()
    {
        return $this->reader;
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
        return $this;
    }
}
