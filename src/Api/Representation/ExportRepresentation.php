<?php declare(strict_types=1);

namespace BulkExport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\JobRepresentation;

class ExportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        $exporter = $this->exporter();
        $owner = $this->owner();
        $job = $this->job();
        return [
            'o:id' => $this->id(),
            'o-bulk:exporter' => $exporter ? $exporter->getReference() : null,
            'o:owner' => $owner ? $owner->getReference() : null,
            'o:job' => $job ? $job->getReference() : null,
            'o-bulk:comment' => $this->comment(),
            'o:status' => $this->status(),
            'o:started' => $this->started(),
            'o:ended' => $this->ended(),
            'o:filename' => $this->filename(),
            'o-bulk:writer_params' => $this->writerParams(),
        ];
    }

    public function getControllerName()
    {
        return 'export';
    }

    public function getJsonLdType()
    {
        return 'o-bulk:Export';
    }

    public function exporter(): ?ExporterRepresentation
    {
        $exporter = $this->resource->getExporter();
        return $exporter
            ? $this->getAdapter('bulk_exporters')->getRepresentation($exporter)
            : null;
    }

    public function owner(): ?\Omeka\Api\Representation\UserRepresentation
    {
        $user = $this->resource->getOwner();
        return $user
            ? $this->getAdapter('users')->getRepresentation($user)
            : null;
    }

    public function job(): ?JobRepresentation
    {
        $job = $this->resource->getJob();
        return $job
            ? $this->getAdapter('jobs')->getRepresentation($job)
            : null;
    }

    public function comment(): string
    {
        return (string) $this->resource->getComment();
    }

    /**
     * Get the filename where data are stored.
     */
    public function filename(bool $absolute = false): ?string
    {
        $filename = $this->resource->getFilename();
        if (!$filename) {
            return null;
        }
        if (!$absolute) {
            return basename($filename);
        }
        if (mb_substr($filename, 0, 1) === '/') {
            return $filename;
        }
        // Relative are inside "files/bulk_export/".
        $config = $this->services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return $basePath . '/bulk_export/' . $filename;
    }

    /**
     * Get the url where data are stored.
     *
     * May be null when there is no file or when stored outside for "files/".
     */
    public function fileUrl(): ?string
    {
        $filepath = $this->filename(true);
        if (!$filepath) {
            return null;
        }

        // Relative are inside "files/bulk_export/".
        $config = $this->services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        if (mb_strpos($filepath, $basePath) !== 0) {
            return null;
        }

        // The path between store and filename is the prefix.
        $dir = pathinfo($filepath, PATHINFO_DIRNAME);
        $filename = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        return $this->getFileUrl(mb_substr($dir, mb_strlen($basePath) + 1), $filename, $extension);
    }

    /**
     * Get the file size.
     */
    public function filesize(): ?int
    {
        $filepath = $this->filename(true);
        return $filepath
            ? filesize($filepath)
            : null;
    }

    public function writerParams(): array
    {
        return $this->resource->getWriterParams() ?: [];
    }

    public function status(): string
    {
        $job = $this->job();
        return $job ? $job->status() : 'ready'; // @translate
    }

    public function statusLabel(): string
    {
        $job = $this->job();
        return $job ? $job->statusLabel() : 'Ready'; // @translate
    }

    public function started(): ?\DateTime
    {
        $job = $this->job();
        return $job ? $job->started() : null;
    }

    public function ended(): ?\DateTime
    {
        $job = $this->job();
        return $job ? $job->ended() : null;
    }

    public function isInProgress(): bool
    {
        $job = $this->job();
        return $job && $job->status() === \Omeka\Entity\Job::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        $job = $this->job();
        return $job && $job->status() === \Omeka\Entity\Job::STATUS_COMPLETED;
    }

    public function logCount(): int
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
