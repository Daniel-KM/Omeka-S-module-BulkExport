<?php declare(strict_types=1);

namespace BulkExport\View\Helper;

use BulkExport\Formatter\Manager as FormatterManager;
use Laminas\View\Helper\AbstractHelper;

class ListFormatters extends AbstractHelper
{
    /**
     * @var \BulkExport\Formatter\Manager as FormatterManager
     */
    protected $formatterManager;

    /**
     * @var array
     */
    protected $formatters;

    public function __construct(FormatterManager $formatterManager, array $formatters)
    {
        $this->formatterManager = $formatterManager;
        $this->formatters = $formatters;
    }

    /**
     * List all formatters or specified ones.
     *
     * @param bool $available Return only the formatters set in the settings.
     * @return array Associative array with extension as key and label as value.
     */
    public function __invoke($available = false): array
    {
        $list = [];
        if ($available) {
            $view = $this->getView();
            $setting = $view->status()->isSiteRequest()
                ? $view->plugin('siteSetting')
                : $view->plugin('setting');
            $listFormatters = $setting('bulkexport_formatters') ?: [];
            $listFormatters = array_intersect(array_keys($this->formatters), $listFormatters);
        } else {
            $listFormatters = array_keys($this->formatters);
        }
        foreach ($listFormatters as $formatter) {
            $list[$formatter] = $this->formatterManager->get($formatter)->getLabel();
        }
        return $list;
    }
}
