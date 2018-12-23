<?php
namespace BulkImport\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(name="bulk_import")
 */
class Import extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @Column(
     *     type="array",
     *     nullable=true
     * )
     */
    protected $readerParams;

    /**
     * @Column(
     *     type="array",
     *     nullable=true
     * )
     */
    protected $processorParams;

    /**
     * @Column(
     *     type="string",
     *     nullable=true
     * )
     */
    protected $status;

    /**
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $started;

    /**
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $ended;

    /**
     * @ManyToOne(
     *     targetEntity=Importer::class,
     *     inversedBy="import",
     *     fetch="EXTRA_LAZY",
     * )
     * @JoinColumn(
     *     nullable=true
     * )
     */
    protected $importer;

    public function getId()
    {
        return $this->id;
    }

    public function setReaderParams($value)
    {
        $this->readerParams = $value;
        return $this;
    }

    public function getReaderParams()
    {
        return $this->readerParams;
    }

    public function setProcessorParams($value)
    {
        $this->processorParams = $value;
        return $this;
    }

    public function getProcessorParams()
    {
        return $this->processorParams;
    }

    public function setStatus($value)
    {
        $this->status = $value;
        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStarted($value)
    {
        $this->started = $value;
        return $this;
    }

    public function getStarted()
    {
        return $this->started;
    }

    public function setEnded($value)
    {
        $this->ended = $value;
        return $this;
    }

    public function getEnded()
    {
        return $this->ended;
    }

    public function setImporter($value)
    {
        $this->importer = $value;
        return $this;
    }

    public function getImporter()
    {
        return $this->importer;
    }
}
