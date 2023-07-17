<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use BulkExport\Traits\OpenDocumentTextTemplateTrait;
use PhpOffice\PhpWord;

class Odt extends AbstractFieldsFormatter
{
    use OpenDocumentTextTemplateTrait;

    protected $label = 'odt';
    protected $extension = 'odt';
    protected $mediaType = 'application/vnd.oasis.opendocument.text';

    /**
     * @var string
     */
    protected $filepath;

    public function format($resources, $output = null, array $options = []): self
    {
        if (!extension_loaded('zip') || !extension_loaded('xml')) {
            $this->logger->err(
                'To process export to "{format}", the php extensions "zip" and "xml" are required.', // @translate
                ['format' => $this->getLabel()]
            );
            $this->hasError = true;
            return $this;
        }
        return parent::format($resources, $output, $options);
    }

    protected function initializeOutput(): self
    {
        $tempDir = $this->services->get('Config')['temp_dir'] ?: sys_get_temp_dir();
        $this->filepath = $this->isOutput
            ? $this->output
            // TODO Use Omeka factory for temp files.
            // TODO Use the method openToBrowser() too.
            // "php://temp" doesn't seem to work.
            : @tempnam($tempDir, 'omk_bke_');
        $this->initializeOpenDocumentText();
        return $this;
    }

    protected function finalizeOutput(): self
    {
        $objWriter = PhpWord\IOFactory::createWriter($this->openDocument, 'ODText');
        $objWriter->save($this->filepath);
        if (!$this->isOutput) {
            $this->content = file_get_contents($this->filepath);
            unlink($this->filepath);
        }
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
