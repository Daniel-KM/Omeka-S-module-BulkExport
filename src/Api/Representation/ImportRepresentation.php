<?php
namespace BulkImport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\JobRepresentation;

class ImportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o-module-bulk:importer' => $this->importer()->getReference(),
            'o-module-bulk:reader_params' => $this->readerParams(),
            'o-module-bulk:processor_params' => $this->processorParams(),
            'o:job' => $this->job(),
            'o:status' => $this->status(),
            'o:started' => $this->started(),
            'o:ended' => $this->ended(),
        ];
    }

    public function getControllerName()
    {
        return 'import';
    }

    public function getJsonLdType()
    {
        return 'o-module-bulk:Import';
    }

    /**
     * @return ImporterRepresentation|null
     */
    public function importer()
    {
        $importer = $this->resource->getImporter();
        return $importer
            ? $this->getAdapter('bulk_importers')->getRepresentation($importer)
            : null;
    }

    /**
     * @return array
     */
    public function readerParams()
    {
        return $this->resource->getReaderParams();
    }

    /**
     * @return array
     */
    public function processorParams()
    {
        return $this->resource->getProcessorParams();
    }

    /**
     * @return JobRepresentation|null
     */
    public function job()
    {
        $job = $this->resource->getJob();
        return $job
        ? $this->getAdapter('jobs')->getRepresentation($job)
        : null;
    }

    /**
     * @return string
     */
    public function status()
    {
        $job = $this->job();
        return $job ? $job->status() : 'Ready'; // @translate
    }

    /**
     * @return \DateTime|null
     */
    public function started()
    {
        $job = $this->job();
        return $job ? $job->started() : null;
    }

    /**
     * @return \DateTime|null
     */
    public function ended()
    {
        $job = $this->job();
        return $job ? $job->ended() : null;
    }

    /**
     * @return int
     */
    public function logCount()
    {
        $job = $this->job();
        if (!$job) {
            return 0;
        }

        $response = $this->getServiceLocator()->get('Omeka\ApiManager')
            ->search('logs', ['job_id' => $job->id(), 'limit' => 0]);
        return $response->getTotalResults();
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        return $url(
            'admin/bulk/id',
            [
                'controller' => $this->getControllerName(),
                'action' => $action,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }
}
