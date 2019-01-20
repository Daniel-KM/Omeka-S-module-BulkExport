<?php
namespace BulkExport\Interfaces;

interface Entry extends \Iterator, \ArrayAccess, \Countable, \JsonSerializable
{
    /**
     * Indicates that the entry has no content, so probably to be skipped.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * {@inheritDoc}
     * @see \Iterator::current()
     *
     * @return array The list of values for the current field of the entry.
     */
    public function current();
}
