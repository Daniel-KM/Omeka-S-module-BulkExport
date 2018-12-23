<?php
namespace Import\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(name="import_log")
 */
class Log extends AbstractEntity
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
    protected $severity;

    /**
     * @Column(
     *     type="string",
     *     nullable=true
     * )
     */
    protected $message;

    /**
     * @Column(
     *     type="array",
     *     nullable=true
     * )
     */
    protected $params;

    /**
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $added;

    /**
     * @OneToOne(
     *     targetEntity="Import\Entity\Import",
     *     fetch="EXTRA_LAZY"
     * )
     * @JoinColumn(
     *     nullable=true
     * )
     */
    protected $import;

    public function getId()
    {
        return $this->id;
    }

    public function setSeverity($value)
    {
        $this->severity = $value;
        return $this;
    }

    public function getSeverity()
    {
        return $this->severity;
    }

    public function setMessage($value)
    {
        $this->message = $value;
        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setParams($value)
    {
        $this->params = $value;
        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setAdded($value)
    {
        $this->added = $value;
        return $this;
    }

    public function getAdded()
    {
        return $this->added;
    }

    public function setImport($value)
    {
        $this->import = $value;
        return $this;
    }

    public function getImport()
    {
        return $this->import;
    }
}
