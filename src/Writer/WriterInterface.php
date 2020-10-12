<?php

namespace BulkExport\Writer;

use Laminas\Log\Logger;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Job\AbstractJob as Job;

/**
 * A writer outputs metadata.
 *
 * It can have a config (implements Configurable) and parameters (implements
 * Parametrizable).
 */
interface WriterInterface
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
     * @return self
     */
    public function setLogger(Logger $logger);

    /**
     * @param Job $job
     * @return self
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
     * @return self
     */
    public function process();
}
