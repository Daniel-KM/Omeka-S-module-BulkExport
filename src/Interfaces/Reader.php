<?php
namespace BulkImport\Interfaces;

interface Reader extends \Iterator
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
     * @return array
     */
    public function getAvailableFields();
}
