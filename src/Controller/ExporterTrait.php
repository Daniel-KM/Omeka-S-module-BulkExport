<?php declare(strict_types=1);

namespace BulkExport\Controller;

use Log\Stdlib\PsrMessage;

trait ExporterTrait
{
    protected function output($resources = null, ?string $resourceName = null)
    {
        if (!$resourceName) {
            $resourceName = $this->resourceNameFromRoute();
            if (!$resourceName) {
                throw new \Omeka\Mvc\Exception\NotFoundException(
                    $this->translate('Unsupported resource type to export.') // @translate
                );
            }
        }

        /** @var \BulkExport\Mvc\Controller\Plugin\ExportFormatter $exportFormatter */
        $exportFormatter = $this->exportFormatter();

        $format = $this->params()->fromRoute('format');
        if (!$exportFormatter->has($format)) {
            throw new \Omeka\Mvc\Exception\NotFoundException((string) new PsrMessage(
                $this->translate('Unsupported format "{format}".'), // @translate
                ['format' => $format]
            ));
        }

        if (!$resources) {
            $resources = $this->resourcesFromRoute($resourceName);
        }

        $resourceLimit = $this->bulkExportLimit();

        // Copied in \ApiInfo\Controller\ApiController::getExportOptions().
        /** @see \BulkExport\Controller\Admin\ExporterController::startAction() */

        // Below are set the same settings in admin/api or site.
        $isSiteRequest = $this->status()->isSiteRequest();
        $settings = $isSiteRequest ? $this->siteSettings() : $this->settings();

        $options = [];
        $options['site_slug'] = $isSiteRequest ? $this->params('site-slug') : null;
        $options['metadata'] = $settings->get('bulkexport_metadata', []);
        $options['metadata_exclude'] = $settings->get('bulkexport_metadata_exclude', []);
        $options['format_fields'] = $settings->get('bulkexport_format_fields', 'name');
        $options['format_generic'] = $settings->get('bulkexport_format_generic', 'string');
        $options['format_resource'] = $settings->get('bulkexport_format_resource', 'url_title');
        $options['format_resource_property'] = $settings->get('bulkexport_format_resource_property', 'dcterms:identifier');
        $options['format_uri'] = $settings->get('bulkexport_format_uri', 'uri_label');
        $options['language'] = $settings->get('bulkexport_language', '');
        $options['template'] = $settings->get('bulkexport_template');
        $options['is_site_request'] = $isSiteRequest;
        $options['resource_type'] = $resourceName;
        $options['limit'] = $resourceLimit;

        return $exportFormatter
            ->get($format)
            ->format($resources, null, $options)
            ->getResponse();
    }

    protected function resourceNameFromRoute(): ?string
    {
        $params = $this->params();

        // Support common modules.
        $resourceType = $params->fromRoute('__CONTROLLER__');
        if (isset(\BulkExport\Formatter\AbstractFormatter::RESOURCES[$resourceType])) {
            return \BulkExport\Formatter\AbstractFormatter::RESOURCES[$resourceType];
        } elseif (in_array($resourceType, [
            'AdvancedSearch\Controller\IndexController',
            'AdvancedSearch\Controller\SearchController',
            'Search\Controller\IndexController',
        ])) {
            // TODO It may be an item set.
            return 'resources';
        } elseif ($resourceType === 'Omeka\Controller\ApiLocal'
            || $resourceType === 'Omeka\Controller\Api'
        ) {
            $resourceName = $params->fromRoute('resource');
            return in_array($resourceName, \BulkExport\Formatter\AbstractFormatter::RESOURCES)
                ? $resourceName
                : null;
        } else {
            // Support module CleanUrl, that kept original route in forward.
            $resourceTypeClean = $params->fromRoute('forward');
            return $resourceTypeClean
                && isset(\BulkExport\Formatter\AbstractFormatter::RESOURCES[$resourceTypeClean['__CONTROLLER__']])
                ? \BulkExport\Formatter\AbstractFormatter::RESOURCES[$resourceTypeClean['__CONTROLLER__']]
                : null;
        }
        return null;
    }

    protected function bulkExportLimit(): int
    {
        $settings = $this->settings();
        $isSiteRequest = $this->status()->isSiteRequest();
        $resourceLimit = $settings->get('bulkexport_limit');
        if ($isSiteRequest) {
            $siteSettings = $this->siteSettings();
            $resourceLimit = $siteSettings->get('bulkexport_limit') ?: $settings->get('bulkexport_limit');
        } else {
        }
        $resourceLimit = (int) $resourceLimit ?: \Omeka\Stdlib\Paginator::PER_PAGE;
        return $resourceLimit;
    }

    protected function resourcesFromRoute(string $resourceName)
    {
        $params = $this->params();

        $isSiteRequest = $this->status()->isSiteRequest();

        $settings = $this->settings();
        if ($isSiteRequest) {
            $siteSettings = $this->siteSettings();
        }

        // This is the direct output, so always limited by the configured limit.
        // TODO Limit with pagination (but here without pagination).
        $resourceLimit = $this->bulkExportLimit();

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
            } elseif ($resourceName === 'resources' && version_compare(\Omeka\Module::VERSION, '4.1', '<')) {
                throw new \Omeka\Mvc\Exception\RuntimeException(
                    $this->translate('A query cannot be used to export "resources" before Omeka S v4.1: set the resource type or use the list of ids instead.') // @translate
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
                    // TODO Is to limit pagination per page really useful in direct output? The api controller does not bypass it.
                    // Don't use the value set inside the query for security.
                    if ($isSiteRequest) {
                        $paginationPerPage = $siteSettings->get('pagination_per_page') ?: $settings->get('pagination_per_page');
                    } else {
                        $paginationPerPage = $settings->get('pagination_per_page');
                    }
                    $resources['per_page'] = $paginationPerPage ?: \Omeka\Stdlib\Paginator::PER_PAGE;
                }
                $resources = $this->api()->search($resourceName, $resources, ['returnScalar' => 'id'])->getContent();
            }
        }

        return $resources;
    }
}
