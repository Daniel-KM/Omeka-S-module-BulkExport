<?php declare(strict_types=1);

namespace BulkExport\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\SiteRepresentation;

class BulkExport extends AbstractHelper
{
    /**
     * The default partial view script for multiple resources.
     */
    const PARTIAL_NAME = 'common/bulk-export';

    /**
     * Output the html to export resources.
     *
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation[]|int[]|array|\Omeka\Api\Representation\AbstractResourceEntityRepresentation|int|null $resourcesOrIdsOrQuery
     *   Types must not be mixed. By default, use the current request query.
     * @param array $options Available options:
     * - site (SiteRepresentation): site to use for urls, instead current one.
     * - resourceType (string): the resource type to use for controllers:
     *   "item", "item-set", "media", "annotation", or "resource".
     *   "resource" cannot be used with a query.
     * - exporters (array): the exporters to use instead of the default ones.
     * - heading (string): the title in the output.
     * - divclass (string): a class to add to the main div.
     * - template (string): the template to use instead of the default one.
     */
    public function __invoke($resourcesOrIdsOrQuery = null, array $options = []): string
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $url = $plugins->get('url');
        $api = $plugins->get('api');
        $translate = $plugins->get('translate');

        $isAdmin = $plugins->get('status')->isAdminRequest();

        // Some options are not options, but added only to set their order.
        $options += [
            'site' => null,
            'resourcesOrIdsOrQuery' => null,
            'resourceType' => '',
            'exporters' => null,
            'urls' => [],
            'labels' => [],
            'heading' => '',
            'divclass' => '',
            'isMultiple' => false,
            'template' => self::PARTIAL_NAME,
        ];

        if (!$isAdmin && is_null($options['site'])) {
            $options['site'] = $this->currentSite();
        }

        if (is_null($options['exporters'])) {
            $options['exporters'] = $plugins->get('bulkExporters')();
        }

        /** @see \BulkExport\Controller\OutputController::output() */
        // Default is query.
        $isQuery = is_null($resourcesOrIdsOrQuery);
        if (!$isQuery && is_array($resourcesOrIdsOrQuery)) {
            // Check all keys: this is a list of ids if all keys are numeric and
            // this is a query if at least one key is not numeric.
            $isQuery = empty($resourcesOrIdsOrQuery)
                || count($resourcesOrIdsOrQuery) > count(array_filter(array_map('is_numeric', array_keys($resourcesOrIdsOrQuery))));
        }

        $options['resourcesOrIdsOrQuery'] = $resourcesOrIdsOrQuery;
        $options['isMultiple'] = is_array($resourcesOrIdsOrQuery);
        if (!$isQuery && !$options['isMultiple']) {
            $resourcesOrIdsOrQuery = [$resourcesOrIdsOrQuery];
        }

        $resourceTypesToNames = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'resource' => 'resources',
            'annotation' => 'annotations',
        ];

        // Prepare the query for the url.
        if ($isQuery) {
            // The query is checked in the controller, not here.
            $query = is_null($resourcesOrIdsOrQuery)
                ? $plugins->get('params')->fromQuery()
                : $resourcesOrIdsOrQuery;
            // The output controller will throw an error in most of the cases
            // when resource is undefined, so throw error here to avoid to
            // create a wrong url.
            if (empty($options['resourceType'])) {
                $params = $plugins->get('params');
                $resourceType = $params->fromRoute('__CONTROLLER__');
                // Support module Clean url.
                if (empty($resourceTypesToNames[$resourceType])) {
                    $resourceType = $params->fromRoute('forward');
                    if (!$resourceType || empty($resourceTypesToNames[$resourceType['__CONTROLLER__']])) {
                        throw new \Omeka\Mvc\Exception\NotFoundException(
                            $view->translate('Unsupported resource type to export.') // @translate
                        );
                    }
                    $resourceType = $resourceType['__CONTROLLER__'];
                }
                $options['resourceType'] = $resourceType;
            }
            if ($query && $options['resourceType'] === 'resource') {
                throw new \Omeka\Mvc\Exception\NotFoundException(
                    $view->translate('A query cannot be used to export "resources": set the resource type or use the list of ids instead.') // @translate
                );
            }
        } else {
            $firstResource = reset($resourcesOrIdsOrQuery);
            $isNumeric = is_numeric($firstResource);
            if (empty($options['resourceType'])) {
                if ($options['isMultiple']) {
                    $options['resourceType'] = 'resource';
                } else {
                    if ($isNumeric) {
                        $firstResource = $api->read('resources', ['id' => $firstResource])->getContent();
                    }
                    $options['resourceType'] = $firstResource->getControllerName();
                }
            } else {
                if (empty($resourceTypesToNames[$options['resourceType']])) {
                    throw new \Omeka\Mvc\Exception\NotFoundException(
                        sprintf(
                            $view->translate('Unsupported resource type to export: %s.'), // @translate
                            $options['resourceType']
                        )
                    );
                }
            }
            if ($isNumeric) {
                $ids = $resourcesOrIdsOrQuery;
            } else {
                $ids = [];
                foreach ($resourcesOrIdsOrQuery as $resource) {
                    $ids[] = $resource->id();
                }
            }
            $query = ['id' => implode(',', array_values(array_unique($ids)))];
        }

        // Prepare urls for each exporters.
        $options['urls'] = [];
        $siteSlug = $options['site'] ? $options['site']->slug() : null;
        if ($options['isMultiple']) {
            $route = $isAdmin ? 'admin/resource-output' : 'site/resource-output';
            foreach (array_keys($options['exporters']) as $format) {
                $options['urls'][$format] = $url($route, [
                    'site-slug' => $siteSlug,
                    'controller' => $options['resourceType'],
                    'format' => $format,
                ], [
                    'query' => $query,
                ]);
            }
        } elseif (!empty($firstResource)) {
            $route = $isAdmin ? 'admin/resource-output-id' : 'site/resource-output-id';
            $resourceId = $firstResource->id();
            foreach (array_keys($options['exporters']) as $format) {
                $options['urls'][$format] = $url($route, [
                    'site-slug' => $siteSlug,
                    'controller' => $options['resourceType'],
                    'format' => $format,
                    'id' => $resourceId,
                ]);
            }
        }

        $options['labels'] = [];
        foreach (array_keys($options['urls']) as $format) {
            $name = $options['exporters'][$format];
            $options['labels'][$format] = in_array($format, ['ods', 'xlsx', 'xls'])
                ? sprintf($translate('Download as spreadsheet %s'), $name) // @translate
                : (in_array($format, ['bib.txt', 'bib.odt'])
                    ? $translate('Download as text') // @translate
                    : sprintf($translate('Download as %s'), $name)); // @translate
        }

        $template = $options['template'];
        unset($options['template']);
        return $template !== self::PARTIAL_NAME && $view->resolver($template)
            ? $view->partial($template, $options)
            : $view->partial(self::PARTIAL_NAME, $options);
    }

    protected function currentSite(): ?SiteRepresentation
    {
        return $this->view->site ?? $this->view->site = $this->view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }
}
