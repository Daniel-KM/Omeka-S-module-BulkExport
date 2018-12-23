<?php
namespace BulkImport\Entry;

use BulkImport\Interfaces\Entry as EntryInterface;

class Entry implements EntryInterface
{
    /**
     * @var array|\Traversable
     */
    protected $data = [];

    /**
     * @var bool
     */
    protected $valid;

    /**
     * @param array $fields
     * @param array $data
     */
    public function __construct(array $fields, array $data)
    {
        $this->init($fields, $data);
    }

    protected function init(array $fields, array $data)
    {
        foreach ($data as $i => $value) {
            $this->data[$fields[$i]] = $value;
        }
    }

    public function isEmpty()
    {
        $data = array_filter($this->data, function ($v) {
            return strlen($v) > 0;
        });
        return count($data) == 0;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->data)) {
            return $this->data[$offset];
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
        return current($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function next()
    {
        $this->valid = next($this->data) !== false;
    }

    public function rewind()
    {
        reset($this->data);
        $this->valid = true;
    }

    public function valid()
    {
        return $this->valid;
    }

    public function count()
    {
        return count($this->data);
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    public function __toString()
    {
        return print_r($this->data, true);
    }
}
