<?php declare(strict_types=1);

namespace BulkExport\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Log\Stdlib\PsrMessage;

class OutputController extends AbstractActionController
{
    public function outputAction()
    {
        $params = $this->params();

        /** @var \BulkExport\Mvc\Controller\Plugin\ExportFormatter $exportFormatter */
        $exportFormatter = $this->exportFormatter();

        $format = $params->fromRoute('format');
        if (!$exportFormatter->has($format)) {
            throw new \Omeka\Mvc\Exception\NotFoundException(new PsrMessage(
                $this->translate('Unsupported format "{format}".'), // @translate
                ['format' => $format]
            ));
        }

        $isSiteRequest = $this->status()->isSiteRequest();
        $settings = $isSiteRequest ? $this->siteSettings() : $this->settings();

        $resourceLimit = $settings->get('bulkexport_limit') ?: 1000;

        $resourceTypes = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'resource' => 'resources',
            'annotation' => 'annotations',
        ];
        $resourceType = $params->fromRoute('resource-type');
        $resourceType = $resourceTypes[$resourceType];

        // Check the id in the route first to manage the direct route.
        $id = $params->fromRoute('id');
        if ($id) {
            $resources = (int) $id;
        } else {
            $id = $params->fromQuery('id');
            if ($id) {
                if (is_array($id)) {
                    $resources = $id;
                    $id = null;
                } elseif (strpos($id, ',')) {
                    $resources = explode(',', $id);
                    $id = null;
                } else {
                    $resources = (int) $id;
                }
                if (is_array($resources)) {
                    $resources = array_values(array_unique(array_filter(array_map('intval', $resources))));
                    if (!$resources) {
                        throw new \Omeka\Mvc\Exception\NotFoundException();
                    }
                }
            } else {
                $resources = $params->fromQuery();
                // Avoid issue when the query contains a page without per_page,
                // for example when copying the url query.
                if (empty($resources['page'])) {
                    unset($resources['page']);
                    unset($resources['per_page']);
                } else {
                    // Don't use the value set inside the query for security.
                    $resources['per_page'] = $settings->get('pagination_per_page') ?: 25;
                }
                // This is the direct output, so it is always limited by the
                // configured limit, so get the ids directly here.
                $resources['limit'] = $resources['per_page'] ?? $resourceLimit;
                $resources = $this->api()->search($resourceType, $resources, ['returnScalar' => 'id'])->getContent();
            }
        }

        // Copied in \ApiInfo\Controller\ApiController::getExportOptions().

        $options = [];
        $options['site_slug'] = $isSiteRequest ? $this->params('site-slug') : null;
        $options['metadata'] = $settings->get('bulkexport_metadata', []);
        $options['metadata_exclude'] = $settings->get('bulkexport_metadata_exclude', []);
        $options['format_fields'] = $settings->get('bulkexport_format_fields', 'name');
        $options['format_generic'] = $settings->get('bulkexport_format_generic', 'string');
        $options['format_resource'] = $settings->get('bulkexport_format_resource', 'url_title');
        $options['format_resource_property'] = $settings->get('bulkexport_format_resource_property', 'dcterms:identifier');
        $options['format_uri'] = $settings->get('bulkexport_format_uri', 'uri_label');
        $options['template'] = $settings->get('bulkexport_template');
        $options['is_admin_request'] = !$isSiteRequest;
        $options['resource_type'] = $resourceType;
        $options['limit'] = $resourceLimit;

        return $exportFormatter
            ->get($format)
            ->format($resources, null, $options)
            ->getResponse($resourceType);
    }
}
