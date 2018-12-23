<?php
namespace BulkImport\Interfaces;

interface Entry extends \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
    /**
     * Indicates that the entry has no content, so probably to be skipped.
     *
     * @return bool
     */
    public function isEmpty();
}
