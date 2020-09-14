<?php

namespace BulkExport\Writer;

use BulkExport\Form\Writer\TextWriterConfigForm;
use Log\Stdlib\PsrMessage;

class TextWriter extends AbstractFieldsWriter
{
    protected $label = 'Text'; // @translate
    protected $extension = 'txt';
    protected $mediaType = 'text/plain';
    protected $configFormClass = TextWriterConfigForm::class;
    protected $paramsFormClass = TextWriterConfigForm::class;

    protected $configKeys = [
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        'query',
    ];

    protected $paramsKeys = [
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        'query',
    ];

    /**
     * @var resource
     */
    protected $handle;

    protected function initializeOutput()
    {
        $this->handle = fopen($this->filepath, 'w+');
        if (!$this->handle) {
            $this->hasError = true;
            $this->getServiceLocator()->get('Omeka\Logger')->err(new PsrMessage(
                'Unable to open output: {error}.', // @translate
                ['error' => error_get_last()['message']]
            ));
        }
        return $this;
    }

    protected function writeFields(array $fields)
    {
        foreach ($fields as $fieldName => $fieldValues) {
            if (!is_array($fieldValues)) {
                $fieldValues = [$fieldValues];
            }
            if ($this->options['format_fields'] === 'label') {
                $fieldName = $this->getFieldLabel($fieldName);
            }
            fwrite($this->handle, $fieldName . "\n");
            foreach ($fieldValues as $fieldValue) {
                fwrite($this->handle, "\t" . $fieldValue . "\n");
            }
        }
        fwrite($this->handle, "\n--\n\n");
        return $this;
    }

    protected function finalizeOutput()
    {
        if (!$this->handle) {
            $this->hasError = true;
            return $this;
        }
        fclose($this->handle);
        return $this;
    }
}
