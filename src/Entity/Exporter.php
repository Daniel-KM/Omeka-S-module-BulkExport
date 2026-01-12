<?php declare(strict_types=1);

namespace BulkExport\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\User;

/**
 * @Entity
 * @Table(
 *     name="bulk_exporter"
 * )
 */
class Exporter extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *     type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var User
     *
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
     * @var string
     *
     * @Column(
     *     type="string",
     *     nullable=false,
     *     length=190
     * )
     */
    protected $label;

    /**
     * @var string|null
     *
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $formatter;

    /**
     * @var string
     *
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     * @deprecated Use $formatter instead.
     */
    protected $writer;

    /**
     * @var array
     *
     * @Column(
     *     type="json",
     *      nullable=false
     * )
     */
    protected $config;

    /**
     * @var Export[]|ArrayCollection
     *
     * @OneToMany(
     *     targetEntity=Export::class,
     *     mappedBy="exporter",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove"},
     *     indexBy="id"
     * )
     */
    protected $exports;

    public function __construct()
    {
        $this->exports = new ArrayCollection;
    }

    public function getId()
    {
        return $this->id;
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

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setFormatter(?string $formatter): self
    {
        $this->formatter = $formatter;
        return $this;
    }

    public function getFormatter(): ?string
    {
        return $this->formatter;
    }

    /**
     * @deprecated No more writer. Use setFormatter() instead.
     */
    public function setWriter(?string $writer): self
    {
        $this->writer = $writer;
        return $this;
    }

    /**
     * @deprecated No more writer. Use getFormatter() instead.
     */
    public function getWriter(): ?string
    {
        return $this->writer;
    }

    public function setConfig($config): self
    {
        $this->config = $config;
        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @return Export[]|ArrayCollection
     */
    public function getExports(): ArrayCollection
    {
        return $this->exports;
    }
}
