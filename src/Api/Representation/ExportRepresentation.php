<?php
namespace BulkExport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\JobRepresentation;

class ExportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o-module-bulk:exporter' => $this->exporter()->getReference(),
            'o-module-bulk:writer_params' => $this->writerParams(),
            'o-module-bulk:filename' => $this->filename(),
            'o:job' => $this->job(),
            'o:status' => $this->status(),
            'o:started' => $this->started(),
            'o:ended' => $this->ended(),
        ];
    }

    public function getControllerName()
    {
        return 'export';
    }

    public function getJsonLdType()
    {
        return 'o-module-bulk:Export';
    }

    /**
     * @return ExporterRepresentation|null
     */
    public function exporter()
    {
        $exporter = $this->resource->getExporter();
        return $exporter
            ? $this->getAdapter('bulk_exporters')->getRepresentation($exporter)
            : null;
    }

    /**
     * @return array
     */
    public function writerParams()
    {
        return $this->resource->getWriterParams();
    }

    /**
     * @return string
     */
    public function filename()
    {
        return $this->resource->getFilename();
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
            'admin/bulk-export/id',
            [
                'controller' => $this->getControllerName(),
                'action' => $action,
                'id' => $this->id(),
            ],
            ['force_canonical' => $canonical]
        );
    }
}
