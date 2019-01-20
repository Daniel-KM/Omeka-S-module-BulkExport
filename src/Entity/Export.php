<?php
namespace BulkExport\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;

/**
 * @Entity
 * @Table(
 *     name="bulk_export"
 * )
 */
class Export extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var Exporter
     * @ManyToOne(
     *     targetEntity=Exporter::class,
     *     inversedBy="export",
     *     fetch="EXTRA_LAZY"
     * )
     * @JoinColumn(
     *     nullable=true
     * )
     */
    protected $exporter;

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
     * @param Exporter $exporter
     * @return self
     */
    public function setExporter(Exporter $exporter)
    {
        $this->exporter = $exporter;
        return $this;
    }

    /**
     * @return \BulkExport\Entity\Exporter
     */
    public function getExporter()
    {
        return $this->exporter;
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
