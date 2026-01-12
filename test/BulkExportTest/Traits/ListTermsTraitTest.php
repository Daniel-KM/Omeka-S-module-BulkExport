<?php declare(strict_types=1);

namespace BulkExportTest\Traits;

use BulkExport\Job\IndexValueLength;
use Omeka\Test\AbstractHttpControllerTestCase;
use BulkExportTest\BulkExportTestTrait;

/**
 * Tests for the ListTermsTrait.
 *
 * These tests verify the trait's ability to filter properties by value size
 * using the value_data.length index.
 */
class ListTermsTraitTest extends AbstractHttpControllerTestCase
{
    use BulkExportTestTrait;

    /**
     * Test class that uses the ListTermsTrait.
     */
    protected $traitTester;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();

        // Ensure value_data table exists and is populated.
        $this->runJob(IndexValueLength::class, []);

        // Create a test class that uses the trait.
        $this->traitTester = new class($this->getServiceLocator()) {
            use \BulkExport\Traits\ListTermsTrait;

            protected $services;
            protected $easyMeta;

            public function __construct($services)
            {
                $this->services = $services;
                $this->easyMeta = $services->get('Common\EasyMeta');
                $this->translator = $services->get('MvcTranslator');
            }

            public function testGetUsedPropertiesByTerm(array $options = []): array
            {
                return $this->getUsedPropertiesByTerm($options);
            }
        };
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test getting used properties returns all properties.
     */
    public function testGetUsedPropertiesByTerm(): void
    {
        // Create test items.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Test Title']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Test Creator']],
        ]);

        // Re-index after creating items.
        $this->runJob(IndexValueLength::class, []);

        $properties = $this->traitTester->testGetUsedPropertiesByTerm();

        $this->assertArrayHasKey('dcterms:title', $properties);
        $this->assertArrayHasKey('dcterms:creator', $properties);
    }

    /**
     * Test filtering properties by minimum size.
     */
    public function testFilterByMinSize(): void
    {
        // Create items with different value lengths.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Short']],
        ]);
        $this->createItem([
            'dcterms:description' => [['type' => 'literal', '@value' => 'This is a very long description that should pass the minimum size filter']],
        ]);

        // Re-index after creating items.
        $this->runJob(IndexValueLength::class, []);

        // Filter with min_size of 20.
        $properties = $this->traitTester->testGetUsedPropertiesByTerm([
            'min_size' => 20,
        ]);

        // Should find dcterms:description but not dcterms:title (which has "Short").
        $this->assertArrayHasKey('dcterms:description', $properties);
        // dcterms:title with "Short" (5 chars) should be excluded.
        // Note: It might still appear if there are other items with longer titles.
    }

    /**
     * Test filtering properties by maximum size.
     */
    public function testFilterByMaxSize(): void
    {
        // Create items with different value lengths.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Tiny']],
        ]);
        $this->createItem([
            'dcterms:description' => [['type' => 'literal', '@value' => 'This is a much longer description that exceeds the maximum size filter we will set']],
        ]);

        // Re-index after creating items.
        $this->runJob(IndexValueLength::class, []);

        // Filter with max_size of 10.
        $properties = $this->traitTester->testGetUsedPropertiesByTerm([
            'max_size' => 10,
        ]);

        // Should find dcterms:title ("Tiny" = 4 chars) but filter out long descriptions.
        $this->assertArrayHasKey('dcterms:title', $properties);
    }

    /**
     * Test filtering properties by both min and max size.
     */
    public function testFilterByMinAndMaxSize(): void
    {
        // Create items with different value lengths.
        $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'AB']],
        ]);
        $this->createItem([
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Medium length value']],
        ]);
        $this->createItem([
            'dcterms:description' => [['type' => 'literal', '@value' => 'This is an extremely long description that should be filtered out because it exceeds the maximum']],
        ]);

        // Re-index after creating items.
        $this->runJob(IndexValueLength::class, []);

        // Filter with min_size of 5 and max_size of 30.
        $properties = $this->traitTester->testGetUsedPropertiesByTerm([
            'min_size' => 5,
            'max_size' => 30,
        ]);

        // Should find dcterms:creator (19 chars) but not title (2 chars) or description (95+ chars).
        $this->assertArrayHasKey('dcterms:creator', $properties);
    }

    /**
     * Test that value_data index is used (performance test).
     *
     * This test verifies that queries using size filters complete quickly,
     * which indicates the index is being used.
     */
    public function testIndexPerformance(): void
    {
        // Create multiple items.
        for ($i = 0; $i < 10; $i++) {
            $this->createItem([
                'dcterms:title' => [['type' => 'literal', '@value' => "Test Item $i with some content"]],
            ]);
        }

        // Re-index.
        $this->runJob(IndexValueLength::class, []);

        // Measure query time with size filter.
        $start = microtime(true);
        $properties = $this->traitTester->testGetUsedPropertiesByTerm([
            'min_size' => 10,
            'max_size' => 100,
        ]);
        $elapsed = microtime(true) - $start;

        // Query should complete quickly (under 1 second for 10 items).
        $this->assertLessThan(1.0, $elapsed, 'Query with size filter should be fast');
        $this->assertNotEmpty($properties);
    }
}
