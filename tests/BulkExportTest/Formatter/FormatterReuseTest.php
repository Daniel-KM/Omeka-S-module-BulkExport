<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\Csv;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * The formatter is a shared service: a second export must not reuse the fields
 * prepared for the previous one.
 */
class FormatterReuseTest extends AbstractHttpControllerTestCase
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

    protected function exportHeaders(Csv $formatter, array $resources, array $metadata): array
    {
        $formatter->format($resources, null, ['metadata' => $metadata]);
        $lines = explode("\n", trim((string) $formatter->getContent(), "\xEF\xBB\xBF\n"));
        return str_getcsv($lines[0]);
    }

    /**
     * @group integration
     */
    public function testSecondExportUsesItsOwnFields(): void
    {
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Reuse']],
        ]);

        $manager = $this->getServiceLocator()->get(\BulkExport\Formatter\Manager::class);
        $this->assertSame($manager->get(Csv::class), $manager->get(Csv::class));

        $formatter = $manager->get(Csv::class);
        $this->assertSame(['o:id'], $this->exportHeaders($formatter, [$item], ['o:id']));
        $this->assertSame(['o:id', 'dcterms:title'], $this->exportHeaders($formatter, [$item], ['o:id', 'dcterms:title']));
    }
}
