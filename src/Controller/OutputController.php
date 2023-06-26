<?php declare(strict_types=1);

namespace BulkExport\Controller;

use Laminas\Mvc\Controller\AbstractActionController;

class OutputController extends AbstractActionController
{
    use ExporterTrait;

    public function indexAction()
    {
        // Via api or api-local.
        return $this->output();
    }

    public function browseAction()
    {
        // Via admin or public resource view for browse list of resources.
        return $this->output();
    }

    public function showAction()
    {
        // Via admin or public resource view for show resource.
        return $this->output();
    }
}
