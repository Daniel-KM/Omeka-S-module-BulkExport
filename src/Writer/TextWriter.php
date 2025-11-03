<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\TextWriterConfigForm;

class TextWriter extends AbstractFieldsWriter
{
    protected $label = 'Text'; // @translate
    protected $extension = 'txt';
    protected $mediaType = 'text/plain';
    protected $configFormClass = TextWriterConfigForm::class;
    protected $paramsFormClass = TextWriterConfigForm::class;

    protected $configKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
    ];

    protected $paramsKeys = [
        'dirpath',
        'filebase',
        'format_fields',
        'format_fields_labels',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'language',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'metadata_shapers',
        'query',
        'zip_files',
        'incremental',
        'include_deleted',
    ];

    /**
     * @var resource
     */
    protected $handle;

    protected function initializeOutput(): self
    {
        $this->handle = fopen($this->filepath, 'w+');
        if ($this->handle) {
            // Prepend the utf-8 bom.
            fwrite($this->handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        } else {
            $this->hasError = true;
            $this->logger->err(
                'Unable to open output: {error}.', // @translate
                ['error' => error_get_last()['message']]
            );
        }
        return $this;
    }

    protected function writeFields(array $fields): self
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

    protected function finalizeOutput(): self
    {
        if (!$this->handle) {
            $this->hasError = true;
            return $this;
        }
        fclose($this->handle);
        return $this;
    }
}
