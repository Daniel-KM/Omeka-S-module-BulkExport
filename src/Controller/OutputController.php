<?php declare(strict_types=1);

namespace BulkExport\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Log\Stdlib\PsrMessage;

class OutputController extends AbstractActionController
{
    public function browseAction()
    {
        return $this->output(false);
    }

    public function showAction()
    {
        return $this->output(true);
    }

    protected function output($isShow)
    {
        $params = $this->params();

        /** @var \BulkExport\Mvc\Controller\Plugin\ExportFormatter $exportFormatter */
        $exportFormatter = $this->exportFormatter();

        $format = $params->fromRoute('format');
        if (!$exportFormatter->has($format)) {
            throw new \Omeka\Mvc\Exception\NotFoundException((string) new PsrMessage(
                $this->translate('Unsupported format "{format}".'), // @translate
                ['format' => $format]
            ));
        }

        $isSiteRequest = $this->status()->isSiteRequest();
        $isAdmin = !$isSiteRequest;

        // This is the direct output, so always limited by the configured limit.
        $settings = $this->settings();
        if ($isAdmin) {
            $resourceLimit = $settings->get('bulkexport_limit');
        } else {
            $siteSettings = $this->siteSettings();
            $resourceLimit = $siteSettings->get('bulkexport_limit') ?: $settings->get('bulkexport_limit');
        }
        $resourceLimit = (int) $resourceLimit ?: 1000;

        $resourceTypesToNames = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'resource' => 'resources',
            'annotation' => 'annotations',
        ];
        $resourceType = $params->fromRoute('__CONTROLLER__');
        // Support common modules.
        if (empty($resourceTypesToNames[$resourceType])) {
            if (in_array($resourceType, ['AdvancedSearch\Controller\IndexController', 'Search\Controller\IndexController'])) {
                // TODO It may be an item set.
                $resourceType = 'resource';
            } else {
                // Support module Clean url.
                $resourceTypeClean = $params->fromRoute('forward');
                if (!$resourceTypeClean || empty($resourceTypesToNames[$resourceTypeClean['__CONTROLLER__']])) {
                    throw new \Omeka\Mvc\Exception\NotFoundException(
                        $this->translate('Unsupported resource type to export.') // @translate
                    );
                }
                $resourceType = $resourceTypeClean['__CONTROLLER__'];
            }
        }
        $resourceName = $resourceTypesToNames[$resourceType];

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
                    $id = (int) $id;
                    $resources = $id;
                }
                if (is_array($resources)) {
                    $resources = array_values(array_unique(array_filter(array_map('intval', $resources))));
                    if (!$resources) {
                        throw new \Omeka\Mvc\Exception\RuntimeException(
                            $this->translate('The list of ids should be a list of numeric internal identifiers.') // @translate
                        );
                    }
                    // This is the direct output, so it is always limited by the
                    // configured limit.
                    $resources = array_slice($resources, 0, $resourceLimit);
                }
            } elseif ($resourceName === 'resources') {
                throw new \Omeka\Mvc\Exception\RuntimeException(
                    $this->translate('A query cannot be used to export "resources": set the resource type or use the list of ids instead.') // @translate
                );
            } else {
                $resources = $params->fromQuery();
                // Avoid issue when the query contains a page without per_page,
                // for example when copying the url query.
                if (empty($resources['page'])) {
                    unset($resources['page']);
                    unset($resources['per_page']);
                    // This is the direct output, so it is always limited by the
                    // configured limit, so get the ids directly here.
                    $resources['limit'] = $resourceLimit;
                } else {
                    // Don't use the value set inside the query for security.
                    if ($isAdmin) {
                        $paginationPerPage = $settings->get('pagination_per_page');
                    } else {
                        $paginationPerPage = $siteSettings->get('pagination_per_page') ?: $settings->get('pagination_per_page');
                    }
                    $resources['per_page'] = $paginationPerPage ?: 25;
                }
                $resources = $this->api()->search($resourceName, $resources, ['returnScalar' => 'id'])->getContent();
            }
        }

        // Copied in \ApiInfo\Controller\ApiController::getExportOptions().
        // @see \BulkExport\Controller\Admin\ExporterController::startAction().

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
        $options['resource_type'] = $resourceName;
        $options['limit'] = $resourceLimit;

        return $exportFormatter
            ->get($format)
            ->format($resources, null, $options)
            ->getResponse($resourceType);
    }
}
