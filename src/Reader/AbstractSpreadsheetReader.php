<?php
namespace BulkExport\Reader;

use BulkExport\Entry\SpreadsheetEntry;

abstract class AbstractSpreadsheetReader extends AbstractReader
{
    protected function currentEntry()
    {
        return new SpreadsheetEntry($this->availableFields, $this->currentData, $this->getParams());
    }
}
