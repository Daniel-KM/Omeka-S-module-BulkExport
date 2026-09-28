<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\Csv;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Export of the persistent identifier of items (module Persistent Identifiers).
 *
 * @see https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport/-/work_items/11
 */
class PersistentIdentifierTest extends AbstractHttpControllerTestCase
{
    use BulkExportTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists('PersistentIdentifiers\Module', false)) {
            $this->markTestSkipped('Requires module Persistent Identifiers.');
        }
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function exportRows(array $items, array $options): array
    {
        $formatter = $this->getServiceLocator()
            ->get(\BulkExport\Formatter\Manager::class)
            ->get(Csv::class);
        $formatter->format($items, null, $options);
        $content = trim((string) $formatter->getContent(), "\xEF\xBB\xBF\n");
        return array_map('str_getcsv', explode("\n", $content));
    }

    /**
     * @group integration
     */
    public function testExportPersistentIdentifier(): void
    {
        $withPid = $this->createItem(['dcterms:title' => [['type' => 'literal', '@value' => 'With pid']]]);
        $withoutPid = $this->createItem(['dcterms:title' => [['type' => 'literal', '@value' => 'Without pid']]]);

        $pid = 'ark:/99999/fk4test' . $withPid->id();
        $this->getServiceLocator()->get('Omeka\Connection')->executeStatement(
            'INSERT INTO `pid_item` (`item_id`, `pid`) VALUES (?, ?)',
            [$withPid->id(), $pid]
        );

        $rows = $this->exportRows([$withPid, $withoutPid], ['metadata' => ['o:id', 'o:pid']]);
        $this->assertSame(['o:id', 'o:pid'], $rows[0]);
        $this->assertSame([(string) $withPid->id(), $pid], $rows[1]);
        $this->assertSame([(string) $withoutPid->id(), ''], $rows[2]);
    }

    /**
     * The persistent identifier is part of the default fields of items.
     */
    public function testPersistentIdentifierIsDefaultFieldOfItems(): void
    {
        $item = $this->createItem(['dcterms:title' => [['type' => 'literal', '@value' => 'Default']]]);
        // Skip the field "url": outside of a request, there is no route match
        // to get the site slug.
        $rows = $this->exportRows([$item], ['resource_types' => ['o:Item'], 'metadata_exclude' => ['url']]);
        $this->assertContains('o:pid', $rows[0]);
    }
}
