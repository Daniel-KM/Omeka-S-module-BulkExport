<?php
namespace BulkExport\Reader;

use Box\Spout\Common\Type;
use BulkExport\Form\Reader\SpreadsheetReaderConfigForm;
use BulkExport\Form\Reader\TsvReaderParamsForm;
use Zend\Form\Form;
use Zend\ServiceManager\ServiceLocatorInterface;

class TsvReader extends CsvReader
{
    protected $label = 'TSV (tab-separated values)'; // @translate
    protected $mediaType = 'text/tab-separated-values';
    protected $spreadsheetType = Type::CSV;
    protected $configFormClass = SpreadsheetReaderConfigForm::class;
    protected $paramsFormClass = TsvReaderParamsForm::class;

    protected $configKeys = [
        'separator',
    ];

    protected $paramsKeys = [
        'filename',
        'separator',
    ];

    public function __construct(ServiceLocatorInterface  $services)
    {
        parent::__construct($services);
        $this->delimiter = "\t";
        $this->enclosure = chr(0);
        $this->escape = chr(0);
    }

    public function handleParamsForm(Form $form)
    {
        parent::handleParamsForm($form);
        $params = $this->getParams();
        $params['delimiter'] = "\t";
        $params['enclosure'] = chr(0);
        $params['escape'] = chr(0);
        $this->setParams($params);
    }


    protected function reset()
    {
        parent::reset();
        $this->delimiter = "\t";
        $this->enclosure = chr(0);
        $this->escape = chr(0);
    }
}
