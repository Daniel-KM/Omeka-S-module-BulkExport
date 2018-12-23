<?php
namespace BulkImport\Interfaces;

use Zend\Form\Form;

interface Parametrizable
{
    public function setParams($params);

    public function getParams();

    public function getParamsFormClass();

    public function handleParamsForm(Form $form);
}
