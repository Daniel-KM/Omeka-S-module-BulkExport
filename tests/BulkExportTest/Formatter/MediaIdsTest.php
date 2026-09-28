<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\Csv;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Regression test for the field "o:media/o:id" of items with a single media.
 *
 * @see https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport/-/work_items/16
 */
class MediaIdsTest extends AbstractHttpControllerTestCase
{
    use BulkExportTestTrait;

    public function setUp(): void
    {
        parent::setUp();
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
    public function testMediaIdsOfItemsWithOneOrSeveralMedia(): void
    {
        $html = fn (string $text): array => ['o:ingester' => 'html', 'html' => "<p>$text</p>"];
        $one = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'One media']],
            'o:media' => [$html('a')],
        ]);
        $two = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Two media']],
            'o:media' => [$html('b'), $html('c')],
        ]);

        $formatter = $this->getServiceLocator()
            ->get(\BulkExport\Formatter\Manager::class)
            ->get(Csv::class);
        $formatter->format([$one, $two], null, [
            'metadata' => ['o:id', 'o:media/o:id'],
            'separator' => ' | ',
        ]);

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim((string) $formatter->getContent(), "\xEF\xBB\xBF\n"))));
        $this->assertCount(3, $rows);

        $mediaIds = fn ($item): string => implode(' | ', array_map(fn ($m) => $m->id(), $item->media()));
        $this->assertSame([(string) $one->id(), $mediaIds($one)], $rows[1]);
        $this->assertSame([(string) $two->id(), $mediaIds($two)], $rows[2]);
        $this->assertNotSame('', $rows[1][1], 'An item with a single media should list it.');
    }
}
