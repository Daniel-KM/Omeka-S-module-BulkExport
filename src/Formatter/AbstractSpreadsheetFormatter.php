<?php declare(strict_types=1);

namespace BulkExport\Formatter;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractSpreadsheetFormatter extends AbstractFieldsFormatter
{
    protected $defaultOptionsSpreadsheet = [
        'separator' => ' | ',
        'has_separator' => true,
        'empty_fields' => true,
    ];

    protected $prependFieldNames = true;

    /**
     * @var \Box\Spout\Writer\WriterInterface
     */
    protected $spreadsheetWriter;

    /**
     * Type of spreadsheet (default to csv).
     *
     * @var \Box\Spout\Common\Type
     */
    protected $spreadsheetType;

    /**
     * @var string
     */
    protected $filepath;

    public function format($resources, $output = null, array $options = []): FormatterInterface
    {
        $options += $this->defaultOptionsSpreadsheet;
        $separator = $options['separator'];
        $options['separator'] = $separator;
        $options['has_separator'] = mb_strlen($separator) > 0;
        $options['only_first'] = !$options['has_separator'];
        return parent::format($resources, $output, $options);
    }

    protected function getDataResource(AbstractResourceEntityRepresentation $resource): array
    {
        $dataResource = parent::getDataResource($resource);
        if ($this->options['has_separator']) {
            foreach ($dataResource as &$values) {
                if (is_array($values)) {
                    $values = implode($this->options['separator'], $values);
                }
            }
        } else {
            foreach ($dataResource as &$values) {
                if (is_array($values)) {
                    $values = reset($values);
                }
            }
        }
        return $dataResource;
    }
}
