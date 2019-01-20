<?php
namespace BulkExport\Writer;

use Box\Spout\Common\Type;
use BulkExport\Form\Writer\SpreadsheetWriterConfigForm;
use BulkExport\Form\Writer\TsvWriterParamsForm;
use Zend\Form\Form;

class TsvWriter extends CsvWriter
{
    protected $label = 'TSV (tab-separated values)'; // @translate
    protected $extension = 'tsv';
    protected $mediaType = 'text/tab-separated-values';
    protected $spreadsheetType = Type::CSV;
    protected $configFormClass = SpreadsheetWriterConfigForm::class;
    protected $paramsFormClass = TsvWriterParamsForm::class;

    protected $configKeys = [
        'separator',
        'metadata',
    ];

    protected $paramsKeys = [
        'separator',
        'metadata',
    ];

    public function handleParamsForm(Form $form)
    {
        parent::handleParamsForm($form);
        $params = $this->getParams();
        $params['delimiter'] = "\t";
        // Unlike import, chr(0) cannot be used, because it's output.
        // Anyway, enclosure and escape are used only when there is a tabulation
        // inside the value, but this is forbidden by the format and normally
        // never exist.
        // TODO Check if the value contains a tabulation before export.
        // TODO Do not use an enclosure for tsv export.
        $params['enclosure'] = self::DEFAULT_ENCLOSURE;
        $params['escape'] = self::DEFAULT_ESCAPE;
        $this->setParams($params);
    }
}
