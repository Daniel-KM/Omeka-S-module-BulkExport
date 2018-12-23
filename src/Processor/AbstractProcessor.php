<?php
namespace Import\Processor;

use Import\Interfaces\Processor;
use Import\Interfaces\Reader;
use Import\Traits\ServiceLocatorAwareTrait;

use Zend\Log\Logger;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractProcessor implements Processor
{
    use ServiceLocatorAwareTrait;
    /**
     * CsvReader constructor.
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
    }

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var Logger
     */
    protected $logger;

    public function setReader(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function getReader()
    {
        return $this->reader;
    }

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}
