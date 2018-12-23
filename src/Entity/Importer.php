<?php
namespace Import\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(name="import_importers")
 */
class Importer extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    public $id;

    /**
     * @Column(type="string", nullable=true)
     */
    public $name;

    /**
     * @Column(type="string", nullable=true)
     */
    public $reader_name;

    /**
     * @Column(type="array", nullable=true)
     */
    public $reader_config;

    /**
     * @Column(type="string", nullable=true)
     */
    public $processor_name;

    /**
     * @Column(type="array", nullable=true)
     */
    public $processor_config;

    public function getId()
    {
        return $this->id;
    }

    public function setId($value)
    {
        $this->id = $value;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($value)
    {
        $this->name = $value;
        return $this;
    }

    public function getReaderName()
    {
        return $this->reader_name;
    }

    public function setReaderName($value)
    {
        $this->reader_name = $value;
        return $this;
    }

    public function getReaderConfig()
    {
        return $this->reader_config;
    }

    public function setReaderConfig($value)
    {
        $this->reader_config = $value;
        return $this;
    }

    public function getProcessorName()
    {
        return $this->processor_name;
    }

    public function setProcessorName($value)
    {
        $this->processor_name = $value;
        return $this;
    }

    public function getProcessorConfig()
    {
        return $this->processor_config;
    }

    public function setProcessorConfig($value)
    {
        $this->processor_config = $value;
        return $this;
    }
}