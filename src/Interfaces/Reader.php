<?php
namespace BulkImport\Interfaces;

interface Reader extends \Iterator
{
    public function getLabel();

    public function getAvailableFields();
}
