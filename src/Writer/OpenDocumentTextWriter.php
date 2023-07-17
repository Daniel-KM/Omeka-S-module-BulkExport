<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Form\Writer\TextWriterConfigForm;
use BulkExport\Traits\OpenDocumentTextTemplateTrait;
use Log\Stdlib\PsrMessage;
use PhpOffice\PhpWord;

class OpenDocumentTextWriter extends AbstractFieldsWriter
{
    use OpenDocumentTextTemplateTrait;

    protected $label = 'OpenDocument Text'; // @translate
    protected $extension = 'odt';
    protected $mediaType = 'application/vnd.oasis.opendocument.text';
    protected $configFormClass = TextWriterConfigForm::class;
    protected $paramsFormClass = TextWriterConfigForm::class;

    public function isValid(): bool
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

    protected function initializeOutput(): self
    {
        $this->initializeOpenDocumentText();
        return $this;
    }

    protected function finalizeOutput(): self
    {
        $objWriter = PhpWord\IOFactory::createWriter($this->openDocument, 'ODText');
        $objWriter->save($this->filepath);
        return $this;
    }

    /**
     * For compatibility with php 7.4, the method is called indirectly.
     */
    protected function writeFields(array $fields): self
    {
        $this->_writeFields($fields);
        return $this;
    }
}
