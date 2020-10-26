<?php declare(strict_types=1);
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
     *     inversedBy="exports",
     *     fetch="EXTRA_LAZY"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $exporter;

    /**
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $comment;

    /**
     * @var Job
     * @OneToOne(
     *     targetEntity=\Omeka\Entity\Job::class
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
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
    protected $writerParams;

    /**
     * @var string
     * @Column(
     *     type="string",
     *     length=255,
     *     nullable=true
     * )
     */
    protected $filename;

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
     * @param string $comment
     * @return self
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
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
     * @param array|\Traversable $writerParams
     * @return self
     */
    public function setWriterParams($writerParams)
    {
        $this->writerParams = $writerParams;
        return $this;
    }

    /**
     * @return array
     */
    public function getWriterParams()
    {
        return $this->writerParams;
    }

    /**
     * @param string $filename
     * @return self
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
}
