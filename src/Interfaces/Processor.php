<?php
namespace BulkExport\Interfaces;

use Zend\Log\Logger;

interface Processor
{
    /**
     * @return string
     */
    public function getLabel();

    /**
     * @param Writer $writer
     */
    public function setWriter(Writer $writer);

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger);

    /**
     * Perform the process.
     */
    public function process();
}
