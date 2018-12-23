<?php
namespace BulkImport\Interfaces;

use Iterator;

interface Reader extends Iterator
{
    /**
     * @var string
     */
    public function getLabel();

    /**
     * List of fields used in the input, for example the first spreadsheet row.
     *
     * It allows to do the mapping in the user interface.
     *
     * Note that these available fields should not be the first output when
     * `rewind()` is called.
     *
     * @return array
     */
    public function getAvailableFields();
}
