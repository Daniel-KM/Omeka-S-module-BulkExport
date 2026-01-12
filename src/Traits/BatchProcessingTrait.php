<?php declare(strict_types=1);

namespace BulkExport\Traits;

/**
 * Trait for batch processing of resources in Formatters.
 *
 * This trait enables Formatters to process large datasets efficiently by:
 * - Processing resources in configurable batches (SQL_LIMIT)
 * - Supporting job callbacks for stop checks and progress reporting
 * - Tracking statistics (processed, succeeded, skipped counts)
 *
 * Used to allow Formatters to handle the same workloads as Writers.
 */
trait BatchProcessingTrait
{
    /**
     * Callback to check if processing should stop (e.g., job cancelled).
     *
     * @var callable|null
     */
    protected $jobCallback;

    /**
     * Callback for progress reporting.
     *
     * @var callable|null
     */
    protected $progressCallback;

    /**
     * Number of resources to process per batch.
     *
     * @var int
     */
    protected $batchSize = 100;

    /**
     * Statistics for the current processing run.
     *
     * @var array
     */
    protected $stats = [
        'processed' => 0,
        'succeeded' => 0,
        'skipped' => 0,
        'total' => 0,
    ];

    /**
     * Set a callback to check if processing should stop.
     *
     * The callback should return true if processing should stop.
     * Typically used with Omeka job's shouldStop() method.
     *
     * Example:
     * ```php
     * $formatter->setJobCallback(fn() => $job->shouldStop());
     * ```
     *
     * @param callable|null $callback Returns true to stop processing.
     * @return self
     */
    public function setJobCallback(?callable $callback): self
    {
        $this->jobCallback = $callback;
        return $this;
    }

    /**
     * Set a callback for progress reporting.
     *
     * The callback receives: (int $processed, int $total, array $stats)
     *
     * Example:
     * ```php
     * $formatter->setProgressCallback(function($processed, $total, $stats) use ($logger) {
     *     $logger->info("{$processed}/{$total} processed");
     * });
     * ```
     *
     * @param callable|null $callback
     * @return self
     */
    public function setProgressCallback(?callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Set the batch size for processing.
     *
     * @param int $size Number of resources per batch.
     * @return self
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = max(1, $size);
        return $this;
    }

    /**
     * Get the current batch size.
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Get processing statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset processing statistics.
     *
     * @return self
     */
    protected function resetStats(): self
    {
        $this->stats = [
            'processed' => 0,
            'succeeded' => 0,
            'skipped' => 0,
            'total' => 0,
        ];
        return $this;
    }

    /**
     * Check if processing should stop.
     *
     * @return bool True if processing should stop.
     */
    protected function shouldStop(): bool
    {
        if ($this->jobCallback === null) {
            return false;
        }
        return (bool) ($this->jobCallback)();
    }

    /**
     * Report progress if a callback is set.
     *
     * @return self
     */
    protected function reportProgress(): self
    {
        if ($this->progressCallback !== null) {
            ($this->progressCallback)(
                $this->stats['processed'],
                $this->stats['total'],
                $this->stats
            );
        }
        return $this;
    }

    /**
     * Increment statistics.
     *
     * @param string $key 'processed', 'succeeded', or 'skipped'
     * @param int $count Amount to increment.
     * @return self
     */
    protected function incrementStat(string $key, int $count = 1): self
    {
        if (isset($this->stats[$key])) {
            $this->stats[$key] += $count;
        }
        return $this;
    }

    /**
     * Process resources in batches.
     *
     * This method iterates through resource IDs in batches, calling the
     * provided callback for each resource. It handles:
     * - Batch loading of resources
     * - Stop checks between batches
     * - Progress reporting
     * - Statistics tracking
     *
     * @param array $resourceIds List of resource IDs to process.
     * @param string $resourceType API resource type (e.g., 'items').
     * @param callable $processResource Callback: function(AbstractResourceEntityRepresentation $resource): bool
     *                                  Returns true if resource was processed successfully.
     * @return self
     */
    protected function processBatches(array $resourceIds, string $resourceType, callable $processResource): self
    {
        $this->resetStats();
        $this->stats['total'] = count($resourceIds);

        $batches = array_chunk($resourceIds, $this->batchSize);

        foreach ($batches as $batchIds) {
            if ($this->shouldStop()) {
                break;
            }

            foreach ($batchIds as $resourceId) {
                if ($this->shouldStop()) {
                    break 2;
                }

                try {
                    $resource = $this->api->read($resourceType, ['id' => $resourceId])->getContent();
                    $success = $processResource($resource);

                    $this->incrementStat('processed');
                    if ($success) {
                        $this->incrementStat('succeeded');
                    } else {
                        $this->incrementStat('skipped');
                    }
                } catch (\Exception $e) {
                    $this->incrementStat('processed');
                    $this->incrementStat('skipped');
                    // Log error if logger available.
                    if (isset($this->logger)) {
                        $this->logger->warn(
                            'Error processing resource #{id}: {error}', // @translate
                            ['id' => $resourceId, 'error' => $e->getMessage()]
                        );
                    }
                }
            }

            $this->reportProgress();

            // Clear entity manager to free memory after each batch.
            if (isset($this->services)) {
                $entityManager = $this->services->get('Omeka\EntityManager');
                $entityManager->clear();
            }
        }

        return $this;
    }

    /**
     * Check if batch processing mode is enabled.
     *
     * Batch mode is enabled when a job callback is set, indicating
     * this is a background job rather than a real-time request.
     *
     * @return bool
     */
    protected function isBatchMode(): bool
    {
        return $this->jobCallback !== null;
    }
}
