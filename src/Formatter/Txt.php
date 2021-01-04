<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Txt extends AbstractFieldsFormatter
{
    protected $label = 'txt';
    protected $extension = 'txt';
    protected $responseHeaders = [
        'Content-type' => 'text/plain',
    ];

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): array
    {
        $dataResource = parent::getDataResource($resource);
        // Remove empty metadata.
        foreach ($dataResource as $key => &$fieldValues) {
            if (!is_array($fieldValues)) {
                $fieldValues = [$fieldValues];
            }
            $fieldValues = array_filter($fieldValues, 'strlen');
            if (!count($fieldValues)) {
                unset($dataResource[$key]);
            }
        }
        return $dataResource;
    }

    protected function initializeOutput(): FormatterInterface
    {
        parent::initializeOutput();
        // Prepend the utf-8 bom.
        if (!$this->hasError) {
            fwrite($this->handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
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
}
