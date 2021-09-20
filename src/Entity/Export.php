<?php declare(strict_types=1);

namespace BulkExport\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;
use Omeka\Entity\User;

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
     * @var User
     * @ManyToOne(
     *     targetEntity=\Omeka\Entity\User::class
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $owner;

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
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $comment;

    /**
     * @var array
     * @Column(
     *     type="json",
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

    public function setExporter(Exporter $exporter): self
    {
        $this->exporter = $exporter;
        return $this;
    }

    public function getExporter(): \BulkExport\Entity\Exporter
    {
        return $this->exporter;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner(): ?\Omeka\Entity\User
    {
        return $this->owner;
    }

    public function setJob(?Job $job): self
    {
        $this->job = $job;
        return $this;
    }

    public function getJob(): ?\Omeka\Entity\Job
    {
        return $this->job;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @param array|\Traversable $writerParams
     */
    public function setWriterParams($writerParams): self
    {
        $this->writerParams = $writerParams;
        return $this;
    }

    public function getWriterParams(): ?array
    {
        return $this->writerParams;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }
}
