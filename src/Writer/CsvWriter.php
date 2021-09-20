<?php declare(strict_types=1);

namespace BulkExport\Writer;

use Box\Spout\Common\Type;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use BulkExport\Form\Writer\CsvWriterConfigForm;

/**
 * Box Spout Spreadshet writer doesn't support escape for csv (even if it
 * manages end of line and encoding). So the basic file handler is used for csv.
 * The format tsv uses the Spout writer, because there is no escape.
 */
class CsvWriter extends AbstractSpreadsheetWriter
{
    const DEFAULT_DELIMITER = ',';
    const DEFAULT_ENCLOSURE = '"';
    const DEFAULT_ESCAPE = '\\';

    protected $label = 'CSV'; // @translate
    protected $extension = 'csv';
    protected $mediaType = 'text/csv';
    protected $configFormClass = CsvWriterConfigForm::class;
    protected $paramsFormClass = CsvWriterConfigForm::class;

    protected $configKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'query',
    ];

    protected $paramsKeys = [
        'delimiter',
        'enclosure',
        'escape',
        'separator',
        'format_fields',
        'format_generic',
        'format_resource',
        'format_resource_property',
        'format_uri',
        'resource_types',
        'metadata',
        'metadata_exclude',
        'query',
    ];

    protected $spreadsheetType = Type::CSV;

    public function isValid(): bool
    {
        if (WriterEntityFactory::createODSWriter() instanceof \Box\Spout\Writer\AbstractWriter) {
            $this->lastErrorMessage = 'The dependency Box/Spout version should be >= 3.0. Upgrade dependencies or CSV Import. See readme.'; // @translate
            return false;
        }
        return parent::isValid();
    }

    protected function initializeOutput()
    {
        parent::initializeOutput();

        $this->spreadsheetWriter
            ->setFieldDelimiter($this->getParam('delimiter', self::DEFAULT_DELIMITER))
            ->setFieldEnclosure($this->getParam('enclosure', self::DEFAULT_ENCLOSURE))
            // The escape character cannot be set with this writer.
            // ->setFieldEscape($this->getParam('escape', self::DEFAULT_ESCAPE))
            // The end of line cannot be set with csv writer (reader only).
            // ->setEndOfLineCharacter("\n")
            ->setShouldAddBOM(true);
        return $this;
    }
}
