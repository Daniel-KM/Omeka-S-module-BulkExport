<?php
namespace Import\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * @Entity
 * @Table(name="import_logs")
 */
class Log extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    public $id;

    /**
     * @Column(type="string", nullable=true)
     */
    public $severity;

    /**
     * @Column(type="string", nullable=true)
     */
    public $message;

    /**
     * @Column(type="array", nullable=true)
     */
    public $params;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $added;

    /**
     * @OneToOne(targetEntity="Import\Entity\Import", fetch="EXTRA_LAZY")
     * @JoinColumn(nullable=true)
     */
    public $import;


    public function getId()
    {
        return $this->id;
    }
    public function setId($value)
    {
        $this->id = $value;
        return $this;
    }

    public function getSeverity()
    {
        return $this->severity;
    }
    public function setSeverity($value)
    {
        $this->severity = $value;
        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }
    public function setMessage($value)
    {
        $this->message = $value;
        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }
    public function setParams($value)
    {
        $this->params = $value;
        return $this;
    }

    public function getAdded()
    {
        return $this->added;
    }
    public function setAdded($value)
    {
        $this->added = $value;
        return $this;
    }

    public function getImport()
    {
        return $this->import;
    }
    public function setImport($value)
    {
        $this->import = $value;
        return $this;
    }
}
