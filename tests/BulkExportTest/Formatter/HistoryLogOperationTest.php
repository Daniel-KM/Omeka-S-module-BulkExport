<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\Csv;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Export of the last operation logged by module History Log.
 *
 * @see https://github.com/Daniel-KM/Omeka-S-module-BulkExport/issues/21
 */
class HistoryLogOperationTest extends AbstractHttpControllerTestCase
{
    use BulkExportTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists('HistoryLog\Module', false)) {
            $this->markTestSkipped('Requires module History Log.');
        }
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * @group integration
     */
    public function testExportLastOperation(): void
    {
        $created = $this->createItem(['dcterms:title' => [['type' => 'literal', '@value' => 'Created']]]);
        $updated = $this->createItem(['dcterms:title' => [['type' => 'literal', '@value' => 'Updated']]]);
        $updated = $this->api()->update('items', $updated->id(), [
            'dcterms:title' => [['type' => 'literal', 'property_id' => 1, '@value' => 'Updated again']],
        ], [], ['isPartial' => true])->getContent();

        $formatter = $this->getServiceLocator()
            ->get(\BulkExport\Formatter\Manager::class)
            ->get(Csv::class);
        $formatter->format([$created, $updated], null, ['metadata' => ['o:id', 'operation']]);
        $content = trim((string) $formatter->getContent(), "\xEF\xBB\xBF\n");
        $rows = array_map('str_getcsv', explode("\n", $content));

        $this->assertSame(['o:id', 'operation'], $rows[0]);
        $this->assertSame([(string) $created->id(), 'create'], $rows[1]);
        $this->assertSame([(string) $updated->id(), 'update'], $rows[2]);
    }
}
