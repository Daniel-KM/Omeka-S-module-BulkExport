<?php
namespace BulkImport\Log;

use BulkImport\Traits\ServiceLocatorAwareTrait;
use Zend\Log\Writer\AbstractWriter;
use Zend\ServiceManager\ServiceLocatorInterface;

class Writer extends AbstractWriter
{
    use ServiceLocatorAwareTrait;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * Writer constructor.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param null $mapping
     * @param null $options
     */
    public function __construct(ServiceLocatorInterface $serviceLocator, $options = null)
    {
        $this->setServiceLocator($serviceLocator);
        parent::__construct($options);
    }

    /**
     * @return \Omeka\Api\Manager
     */
    protected function getApi()
    {
        if ($this->api) {
            return $this->api;
        }
        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        return $this->api;
    }

    /**
     * @param array $event
     */
    protected function doWrite(array $event)
    {
        $params = $event['extra'];
        $import = $params['import'];
        unset($params['import']);
        if (!count($params)) {
            $params = null;
        }

        $data = [
            'severity' => $event['priority'],
            'message' => $event['message'],
            'added' => $event['timestamp'],
            'params' => $params,
            'import' => $import,
        ];
        $this->getApi()->create('bulk_logs', $data);
    }
}
