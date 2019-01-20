<?php
namespace BulkExport\Interfaces;

use Zend\Log\Logger;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * A writer outputs metadata.
 *
 * It can have a config (implements Configurable) and parameters (implements
 * Parametrizable).
 */
interface Writer
{
    /**
     * Writer constructor.
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function __construct(ServiceLocatorInterface $services);

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return string
     */
    public function getExtension();

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger);

    /**
     * @param resource $fh
     */
    public function write($fh);
}
