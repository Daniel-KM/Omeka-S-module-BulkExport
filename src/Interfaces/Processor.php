<?php
namespace BulkImport\Interfaces;

use Zend\Log\Logger;

interface Processor
{
    /**
     * @return string
     */
    public function getLabel();

    /**
     * @param Reader $reader
     */
    public function setReader(Reader $reader);

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger);

    /**
     * Perform the process.
     */
    public function process();
}
