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
     * @param array $options
     */
    public function __construct(array $fields, array $data, array $options = [])
    {
        $this->preInit($fields, $data, $options);
        $this->init($fields, $data, $options);
        $this->postInit($fields, $data, $options);
    }

    protected function preInit(array $fields, array $data, array $options)
    {
        // The set fields should be kept set (for array_key_exists).
        $this->data = [];
        foreach ($fields as $name) {
            $this->data[$name] = null;
        }
    }

    protected function init(array $fields, array $data, array $options)
    {
        // Don't keep data that are not attached to a field.
        // Avoid an issue when the number of data is greater than the number of
        // fields.
        // TODO Collect data without field as garbage (for empty field "")?
        $data = array_slice($data, 0, count($fields), true);

        $data = array_map([$this, 'trimUnicode'], $data);
        foreach ($data as $i => $value) {
            $this->data[$fields[$i]][] = $value;
        }
    }

    protected function postInit(array $options)
    {
        // Filter duplicated and null values.
        foreach ($this->data as &$data) {
            $data = array_unique(array_filter($data, 'strlen'));
        }
    }

    public function isEmpty()
    {
        $data = array_filter($this->data, function ($v) {
            return count(array_filter($v, function ($w) {
                return strlen($w) > 0;
            }));
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

    /**
     * Trim all whitespaces.
     *
     * @param string $string
     * @return string
     */
    protected function trimUnicode($string)
    {
        return preg_replace('/^[\h\v\s[:blank:][:space:]]+|[\h\v\s[:blank:][:space:]]+$/u', '', $string);
    }
}
