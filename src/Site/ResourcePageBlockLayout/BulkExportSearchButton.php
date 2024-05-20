<?php declare(strict_types=1);

namespace BulkExport\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class BulkExportSearchButton implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Bulk export button (search)'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
            'media',
            'item_sets',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        $query = $view->params()->fromQuery() ?: [];

        // Set site early.
        $site = $view->currentSite();
        $query['site_id'] = $site->id();

        // Manage exception for item-set/show early.
        if ($resource->resourceName() === 'item_sets') {
            $query['item_set_id'] = $resource->id();
        }

        // Get all resources of the result, not only the first page.
        // There is a specific limit for the number of resources to output.
        // For longer output, use job process for now.
        unset($query['page'], $query['limit']);

        return $view->partial('common/resource-page-block-layout/bulk-export-search-button', [
            'query' => $query,
        ]);
    }
}
