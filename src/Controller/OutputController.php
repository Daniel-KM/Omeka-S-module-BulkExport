<?php
namespace BulkExport\Controller;

use BulkExport\Formatter\Manager as FormatterManager;
use Log\Stdlib\PsrMessage;
use Zend\Mvc\Controller\AbstractActionController;

class OutputController extends AbstractActionController
{
    /**
     * @var \BulkExport\Formatter\Manager
     */
    protected $formatterManager;

    /**
     * @param FormatterManager $formatterManager
     */
    public function __construct(FormatterManager $formatterManager)
    {
        $this->formatterManager = $formatterManager;
    }

    public function outputAction()
    {
        $params = $this->params();

        $format = $params->fromRoute('format');
        if (!$this->formatterManager->has($format)) {
            throw new \Omeka\Mvc\Exception\NotFoundException(new PsrMessage(
                $this->translate('Unsupported format "{format}".'), // @translate
                ['format' => $format]
            ));
        }

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
            }
        }

        $isSiteRequest = $this->status()->isSiteRequest();

        $options = [];
        $settings = $isSiteRequest ? $this->siteSettings() : $this->settings();
        $options['site_slug'] = $isSiteRequest ? $this->params('site-slug') : null;
        $options['metadata'] = $settings->get('bulkexport_metadata', []);
        $options['format_fields'] = $settings->get('bulkexport_format_fields', 'name');
        $options['format_generic'] = $settings->get('bulkexport_format_generic', 'string');
        $options['format_resource'] = $settings->get('bulkexport_format_resource', 'url_title');
        $options['format_resource_property'] = $settings->get('bulkexport_format_resource_property', 'dcterms:identifier');
        $options['format_uri'] = $settings->get('bulkexport_format_uri', 'uri_label');
        $resourceTypes = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'resource' => 'resources',
            'annotation' => 'annotations',
        ];
        $resourceType = $params->fromRoute('resource-type');
        $resourceType = $resourceTypes[$resourceType];
        $options['resource_type'] = $resourceType;

        $resourceLimit = $settings->get('bulkexport_limit', 1000);
        if ($resourceLimit > 0) {
            $options['limit'] = $resourceLimit;
        }

        /** @var \BulkExport\Formatter\FormatterInterface $formatter */
        $formatter = $this->formatterManager->get($format)
            ->format($resources, null, $options);
        $filename = $this->getFilename($resourceType, $formatter->getExtension(), $id);

        // TODO Use direct output if available (ods and php://output).

        $content = $formatter->getContent();
        if ($content === false) {
            // Detailled results are logged.
            throw new \Omeka\Mvc\Exception\RuntimeException(new PsrMessage(
                'Unable to format resources as {format}.', // @translate
                ['format' => $format]
            ));
        }

        $response = $this->getResponse();
        $response
            ->setContent($content);

        /** @var \Zend\Http\Headers $headers */
        $headers = $response
            ->getHeaders()
            ->addHeaderLine('Content-Disposition: attachment; filename=' . $filename)
            // This is the strlen as bytes, not as character.
            ->addHeaderLine('Content-length: ' . strlen($content))
            // When forcing the download of a file over SSL,IE8 and lower
            // browsers fail if the Cache-Control and Pragma headers are not set.
            // @see http://support.microsoft.com/KB/323308
            ->addHeaderLine('Cache-Control: max-age=0')
            ->addHeaderLine('Expires: 0')
            ->addHeaderLine('Pragma: public');
        foreach ($formatter->getResponseHeaders() as $key => $value) {
            $headers
                ->addHeaderLine($key, $value);
        }

        return $response;
    }

    /**
     * @return string
     */
    protected function getFilename($resourceType, $extension, $resourceId = null)
    {
        return $_SERVER['SERVER_NAME']
            . '-' . $resourceType
            . ($resourceId ? '-' . $resourceId : '')
            . '-' . date('Ymd-His')
            . '.' . $extension;
    }
}
