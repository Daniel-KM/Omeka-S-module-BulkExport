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
    ];

    protected $paramsKeys = [
        'separator',
    ];

    public function handleParamsForm(Form $form)
    {
        parent::handleParamsForm($form);
        $params = $this->getParams();
        $params['delimiter'] = "\t";
        $params['enclosure'] = chr(0);
        $params['escape'] = chr(0);
        $this->setParams($params);
    }
}
