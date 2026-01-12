<?php declare(strict_types=1);

namespace BulkExportTest\Traits;

use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for ResourceFieldsTrait format_fields_labels feature.
 */
class ResourceFieldsTraitTest extends AbstractHttpControllerTestCase
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
    }

    /**
     * Test parsing format_fields_labels configuration.
     */
    public function testParseFormatFieldsLabels(): void
    {
        $mock = $this->getMockForTrait(\BulkExport\Traits\ResourceFieldsTrait::class);

        $input = [
            'Person = dcterms:creator dcterms:contributor',
            'Subject = dcterms:subject dcterms:temporal',
        ];

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('parseFormatFieldsLabels');
        $method->setAccessible(true);
        $method->invoke($mock, $input);

        $mappingProperty = $reflection->getProperty('fieldsLabelsMapping');
        $mappingProperty->setAccessible(true);
        $mapping = $mappingProperty->getValue($mock);

        $this->assertArrayHasKey('Person', $mapping);
        $this->assertEquals(['dcterms:creator', 'dcterms:contributor'], $mapping['Person']);

        $this->assertArrayHasKey('Subject', $mapping);
        $this->assertEquals(['dcterms:subject', 'dcterms:temporal'], $mapping['Subject']);
    }

    /**
     * Test applying field labels order.
     */
    public function testApplyFieldsLabelsOrder(): void
    {
        $mock = $this->getMockForTrait(\BulkExport\Traits\ResourceFieldsTrait::class);

        $input = [
            'Person = dcterms:creator dcterms:contributor',
        ];

        $reflection = new \ReflectionClass($mock);

        // First parse the format
        $parseMethod = $reflection->getMethod('parseFormatFieldsLabels');
        $parseMethod->setAccessible(true);
        $parseMethod->invoke($mock, $input);

        // Now apply the order
        $applyMethod = $reflection->getMethod('applyFieldsLabelsOrder');
        $applyMethod->setAccessible(true);

        $fieldNames = ['o:id', 'dcterms:title', 'dcterms:creator', 'dcterms:contributor', 'dcterms:date'];
        $result = $applyMethod->invoke($mock, $fieldNames);

        // "Person" should be first, followed by remaining fields
        $this->assertEquals('Person', $result[0]);
        // dcterms:creator and dcterms:contributor should not be in the result (merged into Person)
        $this->assertNotContains('dcterms:creator', $result);
        $this->assertNotContains('dcterms:contributor', $result);
        // Other fields should still be present
        $this->assertContains('o:id', $result);
        $this->assertContains('dcterms:title', $result);
        $this->assertContains('dcterms:date', $result);
    }

    /**
     * Test getSourceFieldsForOutput returns correct fields.
     */
    public function testGetSourceFieldsForOutput(): void
    {
        $mock = $this->getMockForTrait(\BulkExport\Traits\ResourceFieldsTrait::class);

        $input = [
            'Person = dcterms:creator dcterms:contributor',
        ];

        $reflection = new \ReflectionClass($mock);

        $parseMethod = $reflection->getMethod('parseFormatFieldsLabels');
        $parseMethod->setAccessible(true);
        $parseMethod->invoke($mock, $input);

        $getSourceMethod = $reflection->getMethod('getSourceFieldsForOutput');
        $getSourceMethod->setAccessible(true);

        // For merged field
        $result = $getSourceMethod->invoke($mock, 'Person');
        $this->assertEquals(['dcterms:creator', 'dcterms:contributor'], $result);

        // For regular field
        $result = $getSourceMethod->invoke($mock, 'dcterms:title');
        $this->assertEquals(['dcterms:title'], $result);
    }

    /**
     * Test empty format_fields_labels handling.
     */
    public function testEmptyFormatFieldsLabels(): void
    {
        $mock = $this->getMockForTrait(\BulkExport\Traits\ResourceFieldsTrait::class);

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('parseFormatFieldsLabels');
        $method->setAccessible(true);
        $method->invoke($mock, []);

        $mappingProperty = $reflection->getProperty('fieldsLabelsMapping');
        $mappingProperty->setAccessible(true);
        $mapping = $mappingProperty->getValue($mock);

        $this->assertEmpty($mapping);
    }

    /**
     * Test invalid format lines are skipped.
     */
    public function testInvalidFormatLinesSkipped(): void
    {
        $mock = $this->getMockForTrait(\BulkExport\Traits\ResourceFieldsTrait::class);

        $input = [
            'Valid = dcterms:title',
            'No equals sign',
            '= empty label',
            'empty fields =',
            '',
        ];

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('parseFormatFieldsLabels');
        $method->setAccessible(true);
        $method->invoke($mock, $input);

        $mappingProperty = $reflection->getProperty('fieldsLabelsMapping');
        $mappingProperty->setAccessible(true);
        $mapping = $mappingProperty->getValue($mock);

        // Only valid line should be parsed
        $this->assertCount(1, $mapping);
        $this->assertArrayHasKey('Valid', $mapping);
    }

    /**
     * Test parsing metadata_shapers in new DataTextarea format.
     */
    public function testParseMetadataShapersNewFormat(): void
    {
        // Create a mock class that includes both the trait and the required property
        $mock = new class {
            use \BulkExport\Traits\ResourceFieldsTrait;
            public $options = [];
        };

        // Set up options with new format metadata_shapers
        $mock->options = [
            'metadata_shapers' => [
                ['metadata' => 'dcterms:title', 'shaper' => 'Uppercase'],
                ['metadata' => 'dcterms:title', 'shaper' => 'Lowercase'],
                ['metadata' => 'dcterms:date', 'shaper' => 'Year'],
            ],
        ];

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('parseMetadataShapers');
        $method->setAccessible(true);
        $method->invoke($mock);

        // Check fieldShapersMap for duplicates
        $shapersMapProperty = $reflection->getProperty('fieldShapersMap');
        $shapersMapProperty->setAccessible(true);
        $shapersMap = $shapersMapProperty->getValue($mock);

        // dcterms:title has multiple shapers, so should create unique field names
        $this->assertArrayHasKey('dcterms:title [Uppercase]', $shapersMap);
        $this->assertArrayHasKey('dcterms:title [Lowercase]', $shapersMap);
        $this->assertEquals('Uppercase', $shapersMap['dcterms:title [Uppercase]']);
        $this->assertEquals('Lowercase', $shapersMap['dcterms:title [Lowercase]']);

        // Check fieldSourcesMap
        $sourcesMapProperty = $reflection->getProperty('fieldSourcesMap');
        $sourcesMapProperty->setAccessible(true);
        $sourcesMap = $sourcesMapProperty->getValue($mock);

        $this->assertArrayHasKey('dcterms:title [Uppercase]', $sourcesMap);
        $this->assertArrayHasKey('dcterms:title [Lowercase]', $sourcesMap);
        $this->assertEquals('dcterms:title', $sourcesMap['dcterms:title [Uppercase]']);
        $this->assertEquals('dcterms:title', $sourcesMap['dcterms:title [Lowercase]']);

        // Check that single shaper field (dcterms:date) uses simple format
        $this->assertArrayHasKey('dcterms:date', $mock->options['metadata_shapers']);
        $this->assertEquals('Year', $mock->options['metadata_shapers']['dcterms:date']);
    }

    /**
     * Test getShaperForField returns correct shaper.
     */
    public function testGetShaperForField(): void
    {
        // Create a mock class that includes both the trait and the required property
        $mock = new class {
            use \BulkExport\Traits\ResourceFieldsTrait;
            public $options = [];
        };

        $reflection = new \ReflectionClass($mock);

        // Set up mappings
        $shapersMapProperty = $reflection->getProperty('fieldShapersMap');
        $shapersMapProperty->setAccessible(true);
        $shapersMapProperty->setValue($mock, [
            'dcterms:title [Uppercase]' => 'Uppercase',
        ]);

        $mock->options = [
            'metadata_shapers' => [
                'dcterms:date' => 'Year',
            ],
        ];

        $method = $reflection->getMethod('getShaperForField');
        $method->setAccessible(true);

        // Test explicit mapping
        $result = $method->invoke($mock, 'dcterms:title [Uppercase]');
        $this->assertEquals('Uppercase', $result);

        // Test fallback to options
        $result = $method->invoke($mock, 'dcterms:date');
        $this->assertEquals('Year', $result);

        // Test field without shaper
        $result = $method->invoke($mock, 'dcterms:creator');
        $this->assertNull($result);
    }

    /**
     * Test addMultipleShaperFields adds duplicate columns.
     */
    public function testAddMultipleShaperFields(): void
    {
        $mock = $this->getMockForTrait(\BulkExport\Traits\ResourceFieldsTrait::class);

        $reflection = new \ReflectionClass($mock);

        // Set up fieldSourcesMap
        $sourcesMapProperty = $reflection->getProperty('fieldSourcesMap');
        $sourcesMapProperty->setAccessible(true);
        $sourcesMapProperty->setValue($mock, [
            'dcterms:title [Uppercase]' => 'dcterms:title',
            'dcterms:title [Lowercase]' => 'dcterms:title',
        ]);

        $method = $reflection->getMethod('addMultipleShaperFields');
        $method->setAccessible(true);

        $fieldNames = ['o:id', 'dcterms:title', 'dcterms:date'];
        $result = $method->invoke($mock, $fieldNames);

        // Should add shaper columns after the original field
        $this->assertContains('dcterms:title', $result);
        $this->assertContains('dcterms:title [Uppercase]', $result);
        $this->assertContains('dcterms:title [Lowercase]', $result);

        // Check order: original field followed by shaper variants
        $titleIndex = array_search('dcterms:title', $result);
        $uppercaseIndex = array_search('dcterms:title [Uppercase]', $result);
        $lowercaseIndex = array_search('dcterms:title [Lowercase]', $result);

        $this->assertLessThan($uppercaseIndex, $titleIndex);
        $this->assertLessThan($lowercaseIndex, $titleIndex);
    }

    /**
     * Test getSourceFieldsForOutput with shaper-suffixed field names.
     */
    public function testGetSourceFieldsForOutputWithShaperSuffix(): void
    {
        $mock = $this->getMockForTrait(\BulkExport\Traits\ResourceFieldsTrait::class);

        $reflection = new \ReflectionClass($mock);

        // Set up fieldSourcesMap
        $sourcesMapProperty = $reflection->getProperty('fieldSourcesMap');
        $sourcesMapProperty->setAccessible(true);
        $sourcesMapProperty->setValue($mock, [
            'dcterms:title [Uppercase]' => 'dcterms:title',
        ]);

        $method = $reflection->getMethod('getSourceFieldsForOutput');
        $method->setAccessible(true);

        // Shaper-suffixed field should return original source field
        $result = $method->invoke($mock, 'dcterms:title [Uppercase]');
        $this->assertEquals(['dcterms:title'], $result);

        // Regular field should return itself
        $result = $method->invoke($mock, 'dcterms:date');
        $this->assertEquals(['dcterms:date'], $result);
    }

    /**
     * Test isPropertyField correctly identifies property fields.
     */
    public function testIsPropertyField(): void
    {
        $mock = new class {
            use \BulkExport\Traits\ResourceFieldsTrait;
            public $options = [];
        };

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('isPropertyField');
        $method->setAccessible(true);

        // Property fields (have colon, not o: or oa: prefix).
        $this->assertTrue($method->invoke($mock, 'dcterms:title'));
        $this->assertTrue($method->invoke($mock, 'dcterms:subject'));
        $this->assertTrue($method->invoke($mock, 'foaf:name'));

        // Non-property fields.
        $this->assertFalse($method->invoke($mock, 'o:id'));
        $this->assertFalse($method->invoke($mock, 'o:is_public'));
        $this->assertFalse($method->invoke($mock, 'oa:hasBody'));
        $this->assertFalse($method->invoke($mock, 'url'));
        $this->assertFalse($method->invoke($mock, 'resource_type'));
    }

    /**
     * Test isValuePerColumnMode returns correct status.
     */
    public function testIsValuePerColumnMode(): void
    {
        $mock = new class {
            use \BulkExport\Traits\ResourceFieldsTrait;
            public $options = [];
        };

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('isValuePerColumnMode');
        $method->setAccessible(true);

        // Default is false.
        $this->assertFalse($method->invoke($mock));

        // When enabled.
        $mock->options['value_per_column'] = true;
        $this->assertTrue($method->invoke($mock));

        // When explicitly disabled.
        $mock->options['value_per_column'] = false;
        $this->assertFalse($method->invoke($mock));
    }

    /**
     * Test getColumnMetadataOptions returns correct options.
     */
    public function testGetColumnMetadataOptions(): void
    {
        $mock = new class {
            use \BulkExport\Traits\ResourceFieldsTrait;
            public $options = [];
        };

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('getColumnMetadataOptions');
        $method->setAccessible(true);

        // Default is empty.
        $this->assertEquals([], $method->invoke($mock));

        // With options set.
        $mock->options['column_metadata'] = ['language', 'datatype'];
        $this->assertEquals(['language', 'datatype'], $method->invoke($mock));

        // With mixed values (should filter empty).
        $mock->options['column_metadata'] = ['language', '', 'visibility', null];
        $result = $method->invoke($mock);
        $this->assertContains('language', $result);
        $this->assertContains('visibility', $result);
        $this->assertNotContains('', $result);
        $this->assertNotContains(null, $result);
    }

    /**
     * Test buildColumnMetadataSuffix builds correct suffix.
     */
    public function testBuildColumnMetadataSuffix(): void
    {
        $mock = new class {
            use \BulkExport\Traits\ResourceFieldsTrait;
            public $options = [];
        };

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('buildColumnMetadataSuffix');
        $method->setAccessible(true);

        // Language only.
        $metadata = ['language' => 'fr', 'datatype' => null, 'visibility' => null];
        $columnMetadata = ['language'];
        $result = $method->invoke($mock, $metadata, $columnMetadata);
        $this->assertEquals(' @fr', $result);

        // Datatype only.
        $metadata = ['language' => null, 'datatype' => 'literal', 'visibility' => null];
        $columnMetadata = ['datatype'];
        $result = $method->invoke($mock, $metadata, $columnMetadata);
        $this->assertEquals(' ^^literal', $result);

        // Visibility (private).
        $metadata = ['language' => null, 'datatype' => null, 'visibility' => false];
        $columnMetadata = ['visibility'];
        $result = $method->invoke($mock, $metadata, $columnMetadata);
        $this->assertEquals(' [private]', $result);

        // Visibility (public) - no suffix.
        $metadata = ['language' => null, 'datatype' => null, 'visibility' => true];
        $columnMetadata = ['visibility'];
        $result = $method->invoke($mock, $metadata, $columnMetadata);
        $this->assertEquals('', $result);

        // Combined.
        $metadata = ['language' => 'en', 'datatype' => 'uri', 'visibility' => false];
        $columnMetadata = ['language', 'datatype', 'visibility'];
        $result = $method->invoke($mock, $metadata, $columnMetadata);
        $this->assertEquals(' @en ^^uri [private]', $result);

        // Empty language (no suffix for empty lang).
        $metadata = ['language' => '', 'datatype' => null, 'visibility' => null];
        $columnMetadata = ['language'];
        $result = $method->invoke($mock, $metadata, $columnMetadata);
        $this->assertEquals('', $result);
    }

    /**
     * Test parseValueMetadataKey correctly parses key.
     */
    public function testParseValueMetadataKey(): void
    {
        $mock = new class {
            use \BulkExport\Traits\ResourceFieldsTrait;
            public $options = [];
        };

        $reflection = new \ReflectionClass($mock);
        $method = $reflection->getMethod('parseValueMetadataKey');
        $method->setAccessible(true);

        // Parse language key.
        $result = $method->invoke($mock, 'lang:fr', ['language']);
        $this->assertEquals('fr', $result['language']);

        // Parse combined key.
        $result = $method->invoke($mock, 'lang:en|type:uri|vis:0', ['language', 'datatype', 'visibility']);
        $this->assertEquals('en', $result['language']);
        $this->assertEquals('uri', $result['datatype']);
        $this->assertFalse($result['visibility']);

        // Parse default key.
        $result = $method->invoke($mock, 'default', []);
        $this->assertNull($result['language']);
        $this->assertNull($result['datatype']);
        $this->assertNull($result['visibility']);
    }
}
