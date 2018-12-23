<?php
namespace Import\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;

/**
 * @Entity
 * @Table(name="import_imports")
 */
class Import extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    public $id;

    /**
     * @Column(type="array", nullable=true)
     */
    public $reader_params;

    /**
     * @Column(type="array", nullable=true)
     */
    public $processor_params;

    /**
     * @Column(type="string", nullable=true)
     */
    public $status;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $started;

    /**
     * @Column(type="datetime", nullable=true)
     */
    public $ended;

    /**
     * @OneToOne(targetEntity="Import\Entity\Importer", fetch="EXTRA_LAZY")
     * @JoinColumn(nullable=true)
     */
    public $importer;

    public function getId() {
        return $this->id;
    }
    public function setId($value) {
        $this->id = $value;
        return $this;
    }

    public function getReaderParams() {
        return $this->reader_params;
    }
    public function setReaderParams($value) {
        $this->reader_params = $value;
        return $this;
    }

    public function getProcessorParams() {
        return $this->processor_params;
    }
    public function setProcessorParams($value) {
        $this->processor_params = $value;
        return $this;
    }

    public function getStatus() {
        return $this->status;
    }
    public function setStatus($value) {
        $this->status = $value;
        return $this;
    }

    public function getStarted() {
        return $this->started;
    }
    public function setStarted($value) {
        $this->started = $value;
        return $this;
    }

    public function getEnded() {
        return $this->ended;
    }
    public function setEnded($value) {
        $this->ended = $value;
        return $this;
    }

    public function getImporter() {
        return $this->importer;
    }
    public function setImporter($value) {
        $this->importer = $value;
        return $this;
    }
}
