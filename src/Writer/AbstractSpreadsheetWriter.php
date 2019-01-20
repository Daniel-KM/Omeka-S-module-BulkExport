<?php
namespace BulkExport\Writer;

use BulkExport\Entry\SpreadsheetEntry;

abstract class AbstractSpreadsheetWriter extends AbstractWriter
{
    public function write($fh)
    {
    }

    protected function currentEntry()
    {
        return new SpreadsheetEntry($this->availableFields, $this->currentData, $this->getParams());
    }
}
