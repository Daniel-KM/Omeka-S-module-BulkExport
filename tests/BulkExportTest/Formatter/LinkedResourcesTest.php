<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\Csv;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Export of the resources linking to the exported resource (JSON-LD @reverse).
 *
 * @see https://github.com/Daniel-KM/Omeka-S-module-BulkExport/issues/10
 */
class LinkedResourcesTest extends AbstractHttpControllerTestCase
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

    protected function exportRows(array $items, array $options): array
    {
        $formatter = $this->getServiceLocator()
            ->get(\BulkExport\Formatter\Manager::class)
            ->get(Csv::class);
        $formatter->format($items, null, $options);
        $content = trim((string) $formatter->getContent(), "\xEF\xBB\xBF\n");
        return array_map('str_getcsv', explode("\n", $content));
    }

    protected function link(int $id, int $propertyId): array
    {
        return [['type' => 'resource', 'property_id' => $propertyId, 'value_resource_id' => $id]];
    }

    /**
     * @group integration
     */
    public function testExportLinkedResources(): void
    {
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $relation = $easyMeta->propertyId('dcterms:relation');
        $isPartOf = $easyMeta->propertyId('dcterms:isPartOf');

        $target = $this->createItem(['dcterms:title' => [['type' => 'literal', '@value' => 'Target']]]);
        $lonely = $this->createItem(['dcterms:title' => [['type' => 'literal', '@value' => 'Lonely']]]);
        $first = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'First']],
            'dcterms:identifier' => [['type' => 'literal', '@value' => 'id-first']],
            'dcterms:relation' => $this->link($target->id(), $relation),
            'dcterms:isPartOf' => $this->link($target->id(), $isPartOf),
        ]);
        $second = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Second']],
            'dcterms:relation' => $this->link($target->id(), $relation),
        ]);

        $rows = $this->exportRows([$target, $lonely], [
            'metadata' => ['o:id', '@reverse/o:id', '@reverse/dcterms:identifier', '@reverse/dcterms:title'],
            'separator' => '|',
        ]);

        $this->assertSame(['o:id', '@reverse/o:id', '@reverse/dcterms:identifier', '@reverse/dcterms:title'], $rows[0]);
        $this->assertSame([
            (string) $target->id(),
            $first->id() . '|' . $second->id(),
            'id-first',
            'First|Second',
        ], $rows[1]);
        $this->assertSame([(string) $lonely->id(), '', '', ''], $rows[2]);
    }

    /**
     * The term of sub-resources may be set with a slash, as in the form.
     *
     * @group integration
     */
    public function testExportTitleOfItemSet(): void
    {
        $itemSet = $this->createTrackedItemSet(['dcterms:title' => [['type' => 'literal', '@value' => 'Collection']]]);
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Item']],
            'o:item_set' => [['o:id' => $itemSet->id()]],
        ]);

        $rows = $this->exportRows([$item], ['metadata' => ['o:id', 'o:item_set/dcterms:title']]);
        $this->assertSame([(string) $item->id(), 'Collection'], $rows[1]);
    }
}
