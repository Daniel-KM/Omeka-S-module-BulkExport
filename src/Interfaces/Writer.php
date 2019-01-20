<?php
namespace BulkExport\Interfaces;

use Omeka\Job\AbstractJob as Job;
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
     * The extension of the output filename.
     *
     * @return string
     */
    public function getExtension();

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger);

    /**
     * @param Job $job
     */
    public function setJob(Job $job);

    /**
     * Check if the params of the writer are valid, for example the filepath.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Get the last error message, in particular to know why writer is invalid.
     *
     * @return string
     */
    public function getLastErrorMessage();

    /**
     * Process the export.
     */
    public function process();
}
