<?php declare(strict_types=1);

namespace BulkExport\View\Helper;

use BulkExport\Formatter\Manager as FormatterManager;
use Laminas\View\Helper\AbstractHelper;

class BulkExporters extends AbstractHelper
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
     * List exporters (formatters) for the current space (admin or site) or all.
     *
     * @param bool $all Return all formatters.
     * @return array Associative array with extension as key and label as value.
     */
    public function __invoke(bool $all = false): array
    {
        static $availables;
        static $full;

        if ($all) {
            if ($full === null) {
                $full = [];
                $listFormatters = array_keys($this->formatters);
                foreach ($listFormatters as $formatter) {
                    $full[$formatter] = $this->formatterManager->get($formatter)->getLabel();
                }
            }
            return $full;
        }

        if ($availables === null) {
            $availables = [];
            $plugins = $this->getView()->getHelperPluginManager();
            $status = $plugins->get('status');
            $setting = $status->isSiteRequest()
                ? $plugins->get('siteSetting')
                : $plugins->get('setting');
            $listFormatters = $setting('bulkexport_formatters') ?: [];
            $listFormatters = array_intersect(array_keys($this->formatters), $listFormatters);
            foreach ($listFormatters as $formatter) {
                $availables[$formatter] = $this->formatterManager->get($formatter)->getLabel();
            }
        }
        return $availables;
    }
}
