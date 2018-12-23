<?php
namespace BulkImport\Reader;

use BulkImport\Entry\SpreadsheetEntry;

abstract class AbstractSpreadsheetReader extends AbstractReader
{
    protected function currentEntry()
    {
        return new SpreadsheetEntry($this->availableFields, $this->currentData, $this->getParams());
    }
}
