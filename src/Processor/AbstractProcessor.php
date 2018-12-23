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
     * This value has no impact on process, but when it is set to "1" (default),
     * the order of internal ids will be in the same order than the input and
     * medias will follow their items. If it is greater, the order will follow
     * the number of entries by resource types. This option is used only for
     * creation.
     * Furthermore, statistics are more precise when this option is "1".
     *
     * @var int
     */
    const ENTRIES_BY_BATCH = 1;

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
