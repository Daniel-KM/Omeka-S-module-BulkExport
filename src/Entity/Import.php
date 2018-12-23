<?php
namespace BulkImport\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;

/**
 * @Entity
 * @Table(
 *     name="bulk_import"
 * )
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
     * @var Importer
     * @ManyToOne(
     *     targetEntity=Importer::class,
     *     inversedBy="import",
     *     fetch="EXTRA_LAZY"
     * )
     * @JoinColumn(
     *     nullable=true
     * )
     */
    protected $importer;

    /**
     * @var Job
     * @OneToOne(
     *     targetEntity=\Omeka\Entity\Job::class
     * )
     * @JoinColumn(
     *     nullable=true
     * )
     */
    protected $job;

    /**
     * @var array
     * @Column(
     *     type="json_array",
     *     nullable=true
     * )
     */
    protected $readerParams;

    /**
     * @var array
     * @Column(
     *     type="json_array",
     *     nullable=true
     * )
     */
    protected $processorParams;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Importer $importer
     * @return self
     */
    public function setImporter(Importer $importer)
    {
        $this->importer = $importer;
        return $this;
    }

    /**
     * @return \BulkImport\Entity\Importer
     */
    public function getImporter()
    {
        return $this->importer;
    }

    /**
     * @param Job $job
     * @return self
     */
    public function setJob(Job $job)
    {
        $this->job = $job;
        return $this;
    }

    /**
     * @return \Omeka\Entity\Job
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * @param array|\Traversable $readerParams
     * @return self
     */
    public function setReaderParams($readerParams)
    {
        $this->readerParams = $readerParams;
        return $this;
    }

    /**
     * @return array
     */
    public function getReaderParams()
    {
        return $this->readerParams;
    }

    /**
     * @param array|\Traversable $processorParams
     * @return self
     */
    public function setProcessorParams($processorParams)
    {
        $this->processorParams = $processorParams;
        return $this;
    }

    /**
     * @return array
     */
    public function getProcessorParams()
    {
        return $this->processorParams;
    }

    /**
     * @param string $status
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }
}
