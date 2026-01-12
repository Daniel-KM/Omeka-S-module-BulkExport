<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\GeoJson;
use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Tests for the GeoJSON Formatter.
 */
class GeoJsonFormatterTest extends AbstractHttpControllerTestCase
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
     * Get formatter instance.
     */
    protected function getFormatter(): GeoJson
    {
        $services = $this->getServiceLocator();
        $formatterManager = $services->get(\BulkExport\Formatter\Manager::class);

        return $formatterManager->get(GeoJson::class);
    }

    /**
     * Test formatter can be instantiated.
     */
    public function testFormatterCanBeInstantiated(): void
    {
        $formatter = $this->getFormatter();

        $this->assertInstanceOf(GeoJson::class, $formatter);
    }

    /**
     * Test formatter returns correct extension.
     */
    public function testFormatterExtension(): void
    {
        $formatter = $this->getFormatter();

        $this->assertEquals('geojson', $formatter->getExtension());
    }

    /**
     * Test formatter returns correct MIME type.
     */
    public function testFormatterMediaType(): void
    {
        $formatter = $this->getFormatter();

        $this->assertEquals('application/geo+json', $formatter->getMediaType());
    }

    /**
     * Test formatter returns correct label.
     */
    public function testFormatterLabel(): void
    {
        $formatter = $this->getFormatter();

        $this->assertNotEmpty($formatter->getLabel());
    }

    /**
     * Test formatter produces valid GeoJSON structure.
     *
     * @group integration
     */
    public function testFormatterProducesValidGeoJson(): void
    {
        // Create test item.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'GeoJSON Test Item']],
        ]);

        $formatter = $this->getFormatter();

        $formatter->format([$item]);
        $output = $formatter->getContent();

        // Verify valid GeoJSON structure.
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded, 'Output should be valid JSON');
        $this->assertArrayHasKey('type', $decoded);
        $this->assertEquals('FeatureCollection', $decoded['type']);
        $this->assertArrayHasKey('features', $decoded);
    }

    /**
     * Test formatter handles empty resource list.
     */
    public function testFormatterHandlesEmptyList(): void
    {
        $formatter = $this->getFormatter();

        $formatter->format([]);
        $output = $formatter->getContent();

        $decoded = json_decode($output, true);
        $this->assertEquals('FeatureCollection', $decoded['type']);
        $this->assertEmpty($decoded['features']);
    }
}
