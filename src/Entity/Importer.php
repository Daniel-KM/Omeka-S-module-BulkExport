<?php
namespace Import\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(name="import_importer")
 */
class Importer extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @Column(
     *     type="string",
     *     nullable=true
     * )
     */
    protected $name;

    /**
     * @Column(
     *     type="string",
     *     nullable=true
     * )
     */
    protected $readerName;

    /**
     * @Column(
     *     type="array",
     *     nullable=true
     * )
     */
    protected $readerConfig;

    /**
     * @Column(
     *     type="string",
     *     nullable=true
     * )
     */
    protected $processorName;

    /**
     * @Column(
     *      type="array",
     *      nullable=true
     * )
     */
    protected $processorConfig;

    public function getId()
    {
        return $this->id;
    }

    public function setName($value)
    {
        $this->name = $value;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setReaderName($value)
    {
        $this->readerName = $value;
        return $this;
    }

    public function getReaderName()
    {
        return $this->readerName;
    }

    public function setReaderConfig($value)
    {
        $this->readerConfig = $value;
        return $this;
    }

    public function getReaderConfig()
    {
        return $this->readerConfig;
    }

    public function setProcessorName($value)
    {
        $this->processorName = $value;
        return $this;
    }

    public function getProcessorName()
    {
        return $this->processorName;
    }

    public function setProcessorConfig($value)
    {
        $this->processorConfig = $value;
        return $this;
    }

    public function getProcessorConfig()
    {
        return $this->processorConfig;
    }
}
