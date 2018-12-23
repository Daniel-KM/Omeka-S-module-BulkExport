<?php
namespace BulkImport\Entity;

use DateTime;
use Omeka\Entity\AbstractEntity;

/**
 * @todo Implement the job inside the import to avoid duplication.
 *
 * @Entity
 * @Table(name="bulk_import")
 */
class Import extends AbstractEntity
{
    // All job statuses are not implemented currently.
    const STATUS_STARTING = 'starting'; // @translate
    // const STATUS_STOPPING = 'stopping'; // @translate
    const STATUS_IN_PROGRESS = 'in_progress'; // @translate
    // const STATUS_STOPPED = 'stopped'; // @translate
    const STATUS_COMPLETED = 'completed'; // @translate
    const STATUS_ERROR = 'error'; // @translate

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

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

    /**
     * @Column(
     *     type="string",
     *     nullable=true,
     *     length=190
     * )
     */
    protected $status;

    /**
     * @var DateTime
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $started;

    /**
     * @var DateTime
     * @Column(
     *     type="datetime",
     *     nullable=true
     * )
     */
    protected $ended;

    /**
     * @var Importer
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

    /**
     * @param array|\Traversable $readerParams
     * @return \BulkImport\Entity\Import
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
     * @return \BulkImport\Entity\Import
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
     * @return \BulkImport\Entity\Import
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

    /**
     * @param DateTime $started
     * @return \BulkImport\Entity\Import
     */
    public function setStarted(DateTime $started)
    {
        $this->started = $started;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getStarted()
    {
        return $this->started;
    }

    /**
     * @param DateTime $ended
     * @return \BulkImport\Entity\Import
     */
    public function setEnded(DateTime $ended)
    {
        $this->ended = $ended;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getEnded()
    {
        return $this->ended;
    }

    /**
     * @param Importer $importer
     * @return \BulkImport\Entity\Import
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
}
