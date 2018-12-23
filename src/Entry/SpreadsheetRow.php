<?php
namespace BulkImport\Entry;

use BulkImport\Interfaces\Entry as EntryInterface;

class SpreadsheetRow implements EntryInterface, \Countable, \JsonSerializable
{
    /**
     * @var array|\Traversable
     */
    protected $row = [];

    /**
     * @var bool
     */
    protected $valid;

    /**
     * @param array $headers
     * @param array $rowData
     */
    public function __construct($headers, $rowData)
    {
        foreach ($rowData as $i => $columnData) {
            $this->row[$headers[$i]] = $columnData;
        }
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->row);
    }

    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->row)) {
            return $this->row[$offset];
        }
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('Modification forbidden'); // @translate
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Modification forbidden'); // @translate
    }

    public function current()
    {
        return current($this->row);
    }

    public function key()
    {
        return key($this->row);
    }

    public function next()
    {
        $this->valid = next($this->row) !== false;
    }

    public function rewind()
    {
        reset($this->row);
        $this->valid = true;
    }

    public function valid()
    {
        return $this->valid;
    }

    public function count()
    {
        return count($this->row);
    }

    public function jsonSerialize()
    {
        return $this->row;
    }

    public function __toString()
    {
        return print_r($this->row, true);
    }
}
