<?php
namespace BulkImport\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;

/**
 * @todo Use standard log instead of a special entity.
 *
 * @Entity
 * @Table(name="bulk_log")
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
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $severity;

    /**
     * @var string
     * @Column(
     *     type="string",
     *     nullable=true
     * )
     */
    protected $message;

    /**
     * @var array
     * @Column(
     *     type="json_array",
     *     nullable=true
     * )
     */
    protected $params;

    /**
     * @var DateTime
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $added;

    /**
     * @var Import
     * @ManyToOne(
     *     targetEntity=Import::class,
     *     fetch="EXTRA_LAZY"
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $import;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $severity
     * @return \BulkImport\Entity\Log
     */
    public function setSeverity($severity)
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * @return string
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param string $message
     * @return \BulkImport\Entity\Log
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param array $params
     * @return \BulkImport\Entity\Log
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param DateTime $added
     * @return \BulkImport\Entity\Log
     */
    public function setAdded(DateTime $added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getAdded()
    {
        return $this->added;
    }

    /**
     * @param Import $import
     * @return \BulkImport\Entity\Log
     */
    public function setImport(Import $import)
    {
        $this->import = $import;
        return $this;
    }

    /**
     * @return \BulkImport\Entity\Import
     */
    public function getImport()
    {
        return $this->import;
    }
}
