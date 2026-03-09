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

    protected function process(): self
    {
        $this->logLongValueFields(1000);
        return parent::process();
    }

    /**
     * Log an initial summary of fields with values exceeding a
     * length limit on the selected resources.
     */
    protected function logLongValueFields(int $maxLength): void
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->services->get('Omeka\Connection');

        $qb = $connection->createQueryBuilder();
        $qb
            ->select(
                'CONCAT(vocabulary.prefix, ":", property.local_name) AS term',
                'COUNT(value.id) AS total'
            )
            ->from('value', 'value')
            ->innerJoin('value', 'property', 'property',
                'property.id = value.property_id')
            ->innerJoin('property', 'vocabulary', 'vocabulary',
                'vocabulary.id = property.vocabulary_id')
            ->andWhere('CHAR_LENGTH(value.value) >= ' . $maxLength)
            ->groupBy('property.id')
            ->orderBy('total', 'DESC');

        $bind = [];
        $types = [];
        if ($this->isId && $this->resourceIds) {
            $qb->andWhere(
                $qb->expr()->in('value.resource_id', ':resource_ids')
            );
            $bind['resource_ids'] = $this->resourceIds;
            $types['resource_ids']
                = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
        }

        $results = $connection
            ->executeQuery($qb, $bind, $types)
            ->fetchAllKeyValue();
        if ($results) {
            $list = [];
            foreach ($results as $term => $count) {
                $list[] = sprintf('%s (%d)', $term, $count);
            }
            $this->logger->warn(
                'Fields with values of {max_length} characters or more that will be skipped: {fields}.', // @translate
                [
                    'max_length' => $maxLength,
                    'fields' => implode(', ', $list),
                ]
            );
        }
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
        if ($this->skippedLongFields) {
            $list = [];
            foreach ($this->skippedLongFields as $field => $count) {
                $list[] = sprintf('%s (%d)', $field, $count);
            }
            $this->logger->warn(
                'Skipped values with more than 1000 characters: {fields}.', // @translate
                ['fields' => implode(', ', $list)]
            );
        }

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
