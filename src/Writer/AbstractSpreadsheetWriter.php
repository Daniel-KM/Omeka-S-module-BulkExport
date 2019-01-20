<?php
namespace BulkExport\Writer;

use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\WriterInterface;
use Log\Stdlib\PsrMessage;

abstract class AbstractSpreadsheetWriter extends AbstractWriter
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var integer
     */
    const SQL_LIMIT = 100;

    /**
     * Type of spreadsheet (default to csv).
     *
     * @var string
     */
    protected $spreadsheetType;

    /**
     * @var array
     */
    protected $usedProperties;

    public function isValid()
    {
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $destinationDir = $basePath . DIRECTORY_SEPARATOR . 'bulk_export';
        if (!$this->checkDestinationDir($destinationDir)) {
            $this->lastErrorMessage = new PsrMessage(
                'Output directory "{folder}" is not writeable.', // @translate
                ['folder' => $destinationDir]
            );
            return false;
        }
        return parent::isValid();
    }

    public function process()
    {
        $writer = WriterFactory::create($this->spreadsheetType);
        $this->initializeWriter($writer);

        $filepath = $this->prepareTempFile();
        $writer
            ->openToFile($filepath);

        $headers = $this->listUsedProperties();
        if (!count($headers)) {
            $this->logger->warn('No headers are used in any resources.'); // @translate
            $writer->close();
            $this->saveFile($filepath);
            return;
        }

        $this->logger->info(
            '{number} different headers are used in all resources.', // @translate
            ['number' => count($headers)]
        );

        $writer
            ->addRow($headers);

        $this->addRows($writer);

        $writer
            ->close();

        $this->saveFile($filepath);
    }

    protected function initializeWriter(WriterInterface $writer)
    {
    }

    protected function addRows(WriterInterface $writer)
    {
        /**
         * @var array $config
         * @var \Omeka\Api\Adapter\ItemAdapter $adapter
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Doctrine\ORM\EntityRepository $repository
         */
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $adapter = $services->get('Omeka\ApiAdapterManager')->get('items');
        $entityManager = $services->get('Omeka\EntityManager');
        $connection = $entityManager->getConnection();
        $repository = $entityManager->getRepository(\Omeka\Entity\Item::class);

        $sql = 'SELECT COUNT(id) FROM item WHERE 1 = 1';
        $stmt = $connection->query($sql);
        $totalToProcess = $stmt->fetchColumn();
        $this->logger->info(
            'Processing export of {number} resources.', // @translate
            ['number' => $totalToProcess]
        );

        $separator = $this->getParam('separator', '');
        $hasSeparator = strlen($separator) > 0;
        if (!$hasSeparator) {
            $this->logger->warn(
                'No separator selected: only the first value of each property of each resource will be output.' // @translate
            );
        }

        $headers = $this->listUsedProperties();

        $criteria = [];

        $offset = 0;
        $totalProcessed = 0;
        $totalSucceed = 0;
        $totalSkipped = 0;
        do {
            if ($this->job->shouldStop()) {
                $this->logger->warn(
                    'The job "Export" was stopped: {processed}/{total} resources processed.', // @translate
                    ['processed' => $totalProcessed, 'total' => $totalToProcess]
                );
                break;
            }

            /** @var \Omeka\Entity\AbstractEntity[] $resources */
            $resources = $repository->findBy($criteria, ['id' => 'ASC'], self::SQL_LIMIT, $offset);
            if (!count($resources)) {
                break;
            }

            // TODO Use SpreadsheetEntry.

            foreach ($resources as $resource) {
                /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
                $resource = $adapter->getRepresentation($resource);

                $dataRow = [];
                if ($hasSeparator) {
                    foreach ($headers as $header) {
                        $values = $resource->value($header, ['all' => true]);
                        $dataRow[] = implode($separator, $values);
                    }
                } else {
                    foreach ($headers as $header) {
                        $value = $resource->value($header);
                        $dataRow[] = (string) $value;
                    }
                }

                // Check if data is empty.
                $check = array_filter($dataRow, function($v) {
                    return (bool) strlen($v);
                });
                if (count($check)) {
                    $writer
                        ->addRow($dataRow);
                    ++$totalSucceed;
                } else {
                    ++$totalSkipped;
                }

                // Avoid memory issue.
                unset($resource);

                // Processed = $offset + $key.
                ++$totalProcessed;
            }

            $this->logger->info(
                '{processed}/{total} resources processed, {succeed} succeed, {skipped} skipped.', // @translate
                ['processed' => $totalProcessed, 'total' => $totalToProcess, 'succeed' => $totalSucceed, 'skipped' => $totalSkipped]
            );

            // Avoid memory issue.
            unset($resources);
            $entityManager->clear();

            $offset += self::SQL_LIMIT;
        } while (true);

        $this->logger->notice(
            '{processed}/{total} resources processed, {succeed} succeed, {skipped} skipped.', // @translate
            ['processed' => $totalProcessed, 'total' => $totalToProcess, 'succeed' => $totalSucceed, 'skipped' => $totalSkipped]
        );
    }

    protected function listUsedProperties()
    {
        if ($this->usedProperties) {
            return $this->usedProperties;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->getServiceLocator()->get('Omeka\Connection');

        // List only properties that are used.
        $qb = $connection->createQueryBuilder();
        $qb
            ->select('DISTINCT(CONCAT(vocabulary.prefix, ":", property.local_name)) AS term')
            ->from('value', 'value')
            ->innerJoin('value', 'property', 'property', 'property.id = value.property_id')
            ->innerJoin('property', 'vocabulary', 'vocabulary', 'vocabulary.id = property.vocabulary_id')
            // Order by id, because Omeka orders them with Dublin Core first.
            ->orderBy('property.id')
        ;
        $stmt = $connection->executeQuery($qb, $qb->getParameters());
        $this->usedProperties = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return $this->usedProperties;
    }
}
