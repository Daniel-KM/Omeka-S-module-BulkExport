<?php declare(strict_types=1);

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
     */
    public function __construct(ServiceLocatorInterface $services);

    public function getLabel(): string;

    /**
     * The extension of the output filename.
     */
    public function getExtension(): ?string;

    public function setLogger(Logger $logger): WriterInterface;

    public function setJob(Job $job): WriterInterface;

    /**
     * Check if the params of the writer are valid, for example the filepath.
     */
    public function isValid(): bool;

    /**
     * Get the last error message, in particular to know why writer is invalid.
     */
    public function getLastErrorMessage(): ?string;

    /**
     * Process the export.
     */
    public function process(): WriterInterface;
}
