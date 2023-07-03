<?php declare(strict_types=1);

namespace BulkExport\Controller;

use Laminas\Http\Response;
use Laminas\View\Model\JsonModel;
use Omeka\View\Model\ApiJsonModel;

/**
 * @todo Use request header "Accept".
 */
class ApiController extends \Omeka\Controller\ApiController
{
    use ExporterTrait;

    public function contextAction()
    {
        return $this->returnError(
            $this->translate('Method not allowed for export for now'), // @translate
            Response::STATUS_CODE_405
        );
    }

    public function get($id)
    {
        $apiJsonModel = parent::get($id);
        return $this->outputResourcesFromApiJsonModel($apiJsonModel);
    }

    public function getList()
    {
        // The route requires an id to build the header links (/api[/:resource[/:id]].:format).
        // So instead a copy of parent method, add a fake id before processing
        // and replace them after.
        $this->getEvent()->getRouteMatch()->setParam('id', '__OMK_BEX__');

        $apiJsonModel = parent::getList();

        // Manage special resource "api_resources".
        if (!$apiJsonModel instanceof ApiJsonModel) {
            return $this->returnError(
                $this->translate('Resource type not allowed for export for now'), // @translate
                Response::STATUS_CODE_405
            );
        }

        // Append the links: get omeka response early.
        /** @var \Laminas\Http\Headers $headers */
        $headers = $this->getResponse()->getHeaders();

        // This is the response of the controller now ($this->getResponse()).
        $response = $this->outputResourcesFromApiJsonModel($apiJsonModel);

        // Update the output headers in all cases, even for error.
        $outputHeaders = $response->getHeaders();
        $link = $headers->get('Link');
        if ($link) {
            $outputHeaders->addHeaderLine(str_replace('/__OMK_BEX__', '', $link->toString()));
        }
        $total = $headers->get('Omeka-S-Total-Results');
        if ($total) {
            $outputHeaders->addHeader($total);
        }

        if ($response instanceof \Laminas\View\Model\JsonModel) {
            return $response;
        }

        // In api, return data inline.
        $disposition = $outputHeaders->get('Content-Disposition');
        if ($disposition) {
            $outputHeaders->removeHeader($disposition);
        }
        $outputHeaders->addHeaderLine('Content-Disposition: inline');

        return $response;
    }

    public function create($data, $fileData = [])
    {
        $apiJsonModel = parent::create($data, $fileData);
        return $this->outputResourcesFromApiJsonModel($apiJsonModel);
    }

    public function update($id, $data)
    {
        $apiJsonModel = parent::update($id, $data);
        return $this->outputResourcesFromApiJsonModel($apiJsonModel);
    }

    public function patch($id, $data)
    {
        $apiJsonModel = parent::patch($id, $data);
        return $this->outputResourcesFromApiJsonModel($apiJsonModel);
    }

    public function delete($id)
    {
        $apiJsonModel = parent::delete($id);
        return $this->outputResourcesFromApiJsonModel($apiJsonModel);
    }

    /**
     * @return \Laminas\View\Model\JsonModel|\Laminas\Http\PhpEnvironment\Response
     */
    protected function outputResourcesFromApiJsonModel(ApiJsonModel $apiJsonModel)
    {
        /**
         * @var \Omeka\Api\Response $apiResponse
         * @var \Omeka\Entity\Resource $resource
         */
        $apiResponse = $apiJsonModel->getApiResponse();

        $resourceName = $apiResponse->getRequest()->getResource();
        //  TODO The check agains the list of managed resources should be managed by the FormatterManager.
        if (!in_array($resourceName, \BulkExport\Formatter\AbstractFormatter::RESOURCES)) {
            return $this->returnError(
                $this->translate('Resource type not allowed for export for now'), // @translate
                Response::STATUS_CODE_405
            );
        }

        // May be a single or multiple resources.
        $resources = $apiResponse->getContent();

        // Store the response directly in the controller.
        $this->response = $this->output($resources, $resourceName);
        return $this->response;
    }

    /**
     * Return error with message.
     *
     * Unlike Omeka, return error as json and don't throw exception.
     *
     * @see https://github.com/omniti-labs/jsend#jsend
     */
    protected function returnError($message, $statusCode = Response::STATUS_CODE_400, array $errors = null): JsonModel
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $result = [
            'status' => 'error',
            'message' => $message,
            'code' => $statusCode,
        ];
        if ($errors) {
            $result['data'] = $errors;
        }
        return new JsonModel($result);
    }
}
