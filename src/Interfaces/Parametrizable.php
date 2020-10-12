<?php declare(strict_types=1);
namespace BulkExport\Interfaces;

use Laminas\Form\Form;

interface Parametrizable
{
    /**
     * @param array|\Traversable $config
     * @return self
     */
    public function setParams($params);

    /**
     * @return array|\Traversable
     */
    public function getParams();

    /**
     * @return string
     */
    public function getParamsFormClass();

    /**
     * @param Form $form
     * @return self
     */
    public function handleParamsForm(Form $form);
}
