<?php
namespace Import\Entry;

use Import\Interfaces\Entry as EntryInterface;

class CsvRow implements EntryInterface
{
    protected $row;
    protected $valid;

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
        throw new \Exception("Modification forbidden"); // @translate
    }

    public function offsetUnset($offset)
    {
        throw new \Exception("Modification forbidden"); // @translate
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
        if (false === next($this->row)) {
            $this->valid = false;
        }
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
}
