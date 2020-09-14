<?php

namespace BulkExport\Writer;

use BulkExport\Form\Writer\TextWriterConfigForm;
use Log\Stdlib\PsrMessage;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use PhpOffice\PhpWord;

class OpenDocumentTextWriter extends AbstractFieldsWriter
{
    protected $label = 'OpenDocument Text'; // @translate
    protected $extension = 'odt';
    protected $mediaType = 'application/vnd.oasis.opendocument.text';
    protected $configFormClass = TextWriterConfigForm::class;
    protected $paramsFormClass = TextWriterConfigForm::class;

    /**
     * @var \PhpOffice\PhpWord\PhpWord
     */
    protected $document;

    /**
     * @var \PhpOffice\PhpWord\Element\Section
     */
    protected $documentSection;

    public function isValid()
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->lastErrorMessage = new PsrMessage(
                'To process export of "{label}", the php extensions "zip" and "xml" are required.', // @translate
                ['label' => $this->getLabel()]
            );
            return false;
        }
        return parent::isValid();
    }

    protected function initializeOutput()
    {
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

    protected function writeFields(array $fields)
    {
        $section = $this->document->addSection();
        // $section = $this->documentSection;
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
                $fieldValue = strip_tags($fieldValue);
                // $section->addText($fieldValue, 'record_metadata');
                if (mb_strlen($fieldValue) < 1000) {
                    $section->addText($fieldValue);
                } else {
                    $this->logger->warn(
                        'Skipped field "{fieldname}" of resource: it contains more than 1000 characters.', // @translate
                        ['fieldname' => $fieldName]
                    );
                }
            }
        }
        $section->addText('--');
        $section->addTextBreak();
        return $this;
    }

    protected function finalizeOutput()
    {
        $objWriter = PhpWord\IOFactory::createWriter($this->document, 'ODText');
        $objWriter->save($this->filepath);
        return $this;
    }
}
