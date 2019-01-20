<?php
namespace BulkExport\Entity;

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
    protected $label;

    /**
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $writerClass;

    /**
     * @var array
     * @Column(
     *     type="json_array",
     *     nullable=true
     * )
     */
    protected $writerConfig;

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

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $label
     * @return self
     */
    public function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $writerClass
     * @return self
     */
    public function setWriterClass($writerClass)
    {
        $this->writerClass = $writerClass;
        return $this;
    }

    /**
     * @return string
     */
    public function getWriterClass()
    {
        return $this->writerClass;
    }

    /**
     * @param array $writerConfig
     * @return self
     */
    public function setWriterConfig($writerConfig)
    {
        $this->writerConfig = $writerConfig;
        return $this;
    }

    /**
     * @return array
     */
    public function getWriterConfig()
    {
        return $this->writerConfig;
    }

    /**
     * @param User $owner
     * @return self
     */
    public function setOwner(User $owner = null)
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner()
    {
        return $this->owner;
    }
}
