<?php
namespace BulkImport\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(name="bulk_importer")
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
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $name;

    /**
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $readerName;

    /**
     * @var array
     * @Column(
     *     type="json_array",
     *     nullable=true
     * )
     */
    protected $readerConfig;

    /**
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $processorName;

    /**
     * @var array
     * @Column(
     *      type="json_array",
     *      nullable=true
     * )
     */
    protected $processorConfig;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return \BulkImport\Entity\Importer
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $readerName
     * @return \BulkImport\Entity\Importer
     */
    public function setReaderName($readerName)
    {
        $this->readerName = $readerName;
        return $this;
    }

    /**
     * @return string
     */
    public function getReaderName()
    {
        return $this->readerName;
    }

    /**
     * @param array $readerConfig
     * @return \BulkImport\Entity\Importer
     */
    public function setReaderConfig($readerConfig)
    {
        $this->readerConfig = $readerConfig;
        return $this;
    }

    /**
     * @return array
     */
    public function getReaderConfig()
    {
        return $this->readerConfig;
    }

    /**
     * @param string $processorName
     * @return \BulkImport\Entity\Importer
     */
    public function setProcessorName($processorName)
    {
        $this->processorName = $processorName;
        return $this;
    }

    /**
     * @return string
     */
    public function getProcessorName()
    {
        return $this->processorName;
    }

    /**
     * @param array $processorConfig
     * @return \BulkImport\Entity\Importer
     */
    public function setProcessorConfig($processorConfig)
    {
        $this->processorConfig = $processorConfig;
        return $this;
    }

    /**
     * @return array
     */
    public function getProcessorConfig()
    {
        return $this->processorConfig;
    }
}
