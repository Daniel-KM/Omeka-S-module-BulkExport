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

        $resourceTypes = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'resource' => 'resources',
            'annotation' => 'annotations',
        ];
        $resourceType = $params->fromRoute('resource-type');
        $resourceType = $resourceTypes[$resourceType];
        $options = ['resource_type' => $resourceType];

        /** @var \BulkExport\Formatter\FormatterInterface $formatter */
        $formatter = $this->formatterManager->get($format)->format($resources, null, $options);

        $content = $formatter->getContent();
        if ($content === false) {
            // Detailled results are logged.
            throw new \Omeka\Mvc\Exception\RuntimeException(new PsrMessage(
                'Unable to format resources as {format}.', // @translate
                ['format' => $format]
            ));
        }

        $filename = $this->getFilename($resourceType, $formatter->getExtension(), $id);
        $response = $this->getResponse();
        $response
            ->setContent($content);

        /** @var \Zend\Http\Headers $headers */
        $headers = $response
            ->getHeaders()
            ->addHeaderLine('Content-Disposition: attachment; filename=' . $filename)
            // This is the strlen as bytes, not as character.
            ->addHeaderLine('Content-length: ' . strlen($content))
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
