<?php

namespace BulkExport\Formatter;

use Log\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use PhpOffice\PhpWord;

class Odt extends AbstractFieldsFormatter
{
    protected $label = 'odt';
    protected $extension = 'odt';
    protected $responseHeaders = [
        'Content-type' => 'application/vnd.oasis.opendocument.text',
    ];

    /**
     * @var string
     */
    protected $filepath;

    /**
     * @var \PhpOffice\PhpWord\PhpWord
     */
    protected $document;

    /**
     * @var \PhpOffice\PhpWord\Element\Section
     */
    protected $documentSection;

    public function format($resources, $output = null, array $options = [])
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->services->get('Omeka\Logger')->err(new PsrMessage(
                'To process export to "{format}", the php extensions "zip" and "xml" are required.', // @translate
                ['format' => $this->getLabel()]
            ));
            $this->hasError = false;
            $resources = false;
        }
        return parent::format($resources, $output, $options);
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource)
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

    protected function initializeOutput()
    {
        $tempDir = $this->services->get('Config')['temp_dir'] ?: sys_get_temp_dir();
        $this->filepath = $this->isOutput
            ? $this->output
            // TODO Use Omeka factory for temp files.
            // TODO Use the method openToBrowser() too.
            // "php://temp" doesn't seem to work.
            : tempnam($tempDir, 'omk_export_');
        $this->document = new PhpWord\PhpWord();
        $this->document
            ->addFontStyle(
                'record_label',
                ['name' => 'Tahoma', 'size' => 12, 'color' => '1B2232', 'bold' => true]
            );
        $this->document
            ->addFontStyle(
                'record_metadata',
                ['name' => 'Tahoma', 'size' => 12]
            );
        $this->documentSection = $this->document->addSection();
        return $this;
    }

    protected function writeFields(array $fields)
    {
        // $section = $this->document->addSection();
        $section = $this->documentSection;
        foreach ($fields as $fieldName => $fieldValues) {
            if (!is_array($fieldValues)) {
                $fieldValues = [$fieldValues];
            }
            if ($this->options['format_fields'] === 'label') {
                $fieldName = $this->getFieldLabel($fieldName);
            }
            // $section->addText($fieldName, 'record_label');
            $section->addText($fieldName);
            foreach ($fieldValues as $fieldValue) {
                // $section->addText($fieldValue, 'record_metadata');
                $section->addText($fieldValue);
            }
        }
        $section->addText('--');
        return $this;
    }

    protected function finalizeOutput()
    {
        $objWriter = PhpWord\IOFactory::createWriter($this->document, 'ODText');
        $objWriter->save($this->filepath);
        if (!$this->isOutput) {
            $this->content = file_get_contents($this->filepath);
            unlink($this->filepath);
        }
        return $this;
    }
}
