<?php declare(strict_types=1);

namespace BulkExport\Controller;

use Laminas\Http\Response;
use Laminas\View\Model\JsonModel;
use Omeka\View\Model\ApiJsonModel;

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
        $response = parent::get($id);
        return $this->outputResourcesFromApiJsonModel($response);
    }

    public function getList()
    {
        $response = parent::getList();

        // Manage special resource "api_resources".
        if (!$response instanceof ApiJsonModel) {
            return $this->returnError(
                $this->translate('Resource type not allowed for export for now'), // @translate
                Response::STATUS_CODE_405
            );
        }

        return $this->outputResourcesFromApiJsonModel($response);
    }

    public function create($data, $fileData = [])
    {
        $response = parent::create($data, $fileData);
        return $this->outputResourcesFromApiJsonModel($response);
    }

    public function update($id, $data)
    {
        $response = parent::update($id, $data);
        return $this->outputResourcesFromApiJsonModel($response);
    }

    public function patch($id, $data)
    {
        $response = parent::patch($id, $data);
        return $this->outputResourcesFromApiJsonModel($response);
    }

    public function delete($id)
    {
        $response = parent::delete($id);
        return $this->outputResourcesFromApiJsonModel($response);
    }

    protected function outputResourcesFromApiJsonModel(ApiJsonModel $response)
    {
        /**
         * @var \Omeka\Api\Response $apiResponse
         * @var \Omeka\Entity\Resource $resource
         */
        $apiResponse = $response->getApiResponse();

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
        return $this->output($resources, $resourceName);
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
