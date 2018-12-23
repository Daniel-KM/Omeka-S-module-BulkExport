<?php
namespace Import\Interfaces;

use Zend\Form\Form;

interface Configurable
{
    public function setConfig($config);

    public function getConfig();

    public function getConfigFormClass();

    public function handleConfigForm(Form $form);
}
