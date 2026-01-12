<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\Json;
use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Tests for the JSON Formatter.
 */
class JsonFormatterTest extends AbstractHttpControllerTestCase
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
    protected function getFormatter(): Json
    {
        $services = $this->getServiceLocator();
        $formatterManager = $services->get(\BulkExport\Formatter\Manager::class);

        return $formatterManager->get(Json::class);
    }

    /**
     * Test formatter can be instantiated.
     */
    public function testFormatterCanBeInstantiated(): void
    {
        $formatter = $this->getFormatter();

        $this->assertInstanceOf(Json::class, $formatter);
    }

    /**
     * Test formatter returns correct extension.
     */
    public function testFormatterExtension(): void
    {
        $formatter = $this->getFormatter();

        $this->assertEquals('json', $formatter->getExtension());
    }

    /**
     * Test formatter returns correct MIME type.
     */
    public function testFormatterMediaType(): void
    {
        $formatter = $this->getFormatter();

        $this->assertEquals('application/json', $formatter->getMediaType());
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
     * Test formatter produces valid JSON.
     *
     * @group integration
     */
    public function testFormatterProducesValidJson(): void
    {
        // Create test item.
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'JSON Test Item']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Test Author']],
        ]);

        $formatter = $this->getFormatter();

        // Format single resource and get content.
        $formatter->format([$item]);
        $output = $formatter->getContent();

        // Verify valid JSON.
        $decoded = json_decode($output, true);
        $this->assertNotNull($decoded, 'Output should be valid JSON');
        $this->assertIsArray($decoded);
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
        $this->assertIsArray($decoded);
        $this->assertEmpty($decoded);
    }
}
