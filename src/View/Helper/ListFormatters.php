<?php declare(strict_types=1);

namespace BulkExport\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * @deprecated Use BulkExporters instead (same output, but inverted argument).
 */
class ListFormatters extends AbstractHelper
{
    /**
     * List all formatters or specified ones.
     *
     * @param bool $available Return formatters set in current settings list.
     * @return array Associative array with extension as key and label as value.
     */
    public function __invoke(bool $available = false): array
    {
        return $this->getView()->bulkExporters(!$available);
    }
}
