<?php declare(strict_types=1);

namespace BulkExport\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class BulkExportController extends AbstractActionController
{
    public function indexAction()
    {
        // Exporters.
        $response = $this->api()->search('bulk_exporters', ['sort_by' => 'label', 'sort_order' => 'asc']);
        $exporters = $response->getContent();

        $this->setBrowseDefaults('label', 'asc');

        // Exports.
        $perPage = 25;
        $query = [
            'page' => 1,
            'per_page' => $perPage,
            'sort_by' => 'id',
            'sort_order' => 'desc',
        ];
        $response = $this->api()->search('bulk_exports', $query);
        $this->paginator($response->getTotalResults(), 1);

        $exports = $response->getContent();

        return new ViewModel([
            'exporters' => $exporters,
            'exports' => $exports,
        ]);
    }
}
