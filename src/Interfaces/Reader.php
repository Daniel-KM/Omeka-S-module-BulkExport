<?php
namespace Import\Interfaces;

interface Reader extends \Iterator
{
    public function getLabel();
    public function getAvailableFields();
}
