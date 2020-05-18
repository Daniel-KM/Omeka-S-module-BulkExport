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

        $resourceTypes = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'media' => 'media',
            'resource' => 'resources',
            'annotation' => 'annotations',
        ];

        // Check the id in the route first to manage the direct route.
        $id = $params->fromRoute('id');
        if ($id) {
            // The original controller is lost in routing, so it's simpler to
            // check it directly here. The simplest way to get it is to prepare
            // a route match and to get the controller. An other way is to add a
            // specific controller may be build for each resource type.
            // Currently, just a string check is done.
            $path = $this->getRequest()->getUri()->getPath();
            $resourceType = basename(dirname($path));
            // Take the case where the last part are the id and the action.
            if (!isset($resourceTypes[$resourceType])) {
                $resourceType = basename(dirname(dirname($path)));
            }
            $isSingle = true;
            $isId = true;
            $resources = [$id];
        } else {
            $id = $params->fromQuery('id');
            $resourceType = $params->fromRoute('resource-type');
            if ($id) {
                $isId = true;
                if (is_array($id)) {
                    $isSingle = false;
                    $resources = $id;
                } elseif (strpos($id, ',')) {
                    $isSingle = false;
                    $resources = explode(',', $id);
                } else {
                    $isSingle = true;
                    $resources = [$id];
                }
            } else {
                $isSingle = false;
                $isId = false;
                $resources = $params->fromQuery();
            }
        }

        // Some quick checks.
        if ($isId) {
            $resources = array_values(array_unique(array_filter(array_map('intval', $resources))));
            if (!$resources) {
                throw new \Omeka\Mvc\Exception\NotFoundException();
            }
        }

        if (!isset($resourceTypes[$resourceType])) {
            throw new \Omeka\Mvc\Exception\NotFoundException(new PsrMessage(
                $this->translate('Unsupported resource type "{type}".'), // @translate
                ['type' => $resourceType]
            ));
        }

        $resourceType = $resourceTypes[$resourceType];
        $options = ['resource_type' => $resourceType];

        if ($isSingle) {
            $resources = reset($resources);
        }

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

        $filename = $this->getFilename($resourceType, $formatter->getExtension(), $isSingle ? $id : null);
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
        foreach ($formatter->getHeaders() as $key => $value) {
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
