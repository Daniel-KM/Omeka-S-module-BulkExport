<?php declare(strict_types=1);

namespace BulkExportTest\Formatter;

use BulkExport\Formatter\Odt;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for the ODT Formatter, the only one that uses PhpWord.
 */
class OdtFormatterTest extends AbstractHttpControllerTestCase
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

    protected function getFormatter(): Odt
    {
        return $this->getServiceLocator()
            ->get(\BulkExport\Formatter\Manager::class)
            ->get(Odt::class);
    }

    /**
     * Open the odt content as a zip and return its parts.
     */
    protected function readOdt(string $content): array
    {
        $this->assertStringStartsWith('PK', $content, 'Output should be a zip archive');

        $filepath = tempnam(sys_get_temp_dir(), 'omk_bke_test_');
        file_put_contents($filepath, $content);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($filepath) === true, 'Output should be a valid zip archive');
        $parts = [
            'mimetype' => $zip->getFromName('mimetype'),
            'content.xml' => $zip->getFromName('content.xml'),
        ];
        $zip->close();
        unlink($filepath);

        return $parts;
    }

    public function testFormatterCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Odt::class, $this->getFormatter());
    }

    public function testFormatterExtension(): void
    {
        $this->assertEquals('odt', $this->getFormatter()->getExtension());
    }

    public function testFormatterMediaType(): void
    {
        $this->assertEquals('application/vnd.oasis.opendocument.text', $this->getFormatter()->getMediaType());
    }

    public function testFormatterLabel(): void
    {
        $this->assertNotEmpty($this->getFormatter()->getLabel());
    }

    /**
     * @group integration
     */
    public function testFormatterProducesValidOdt(): void
    {
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'ODT Test Item']],
            'dcterms:creator' => [['type' => 'literal', '@value' => 'Test Author']],
        ]);

        // Skip the field "url": outside of a request, there is no route match
        // to get the site slug.
        $formatter = $this->getFormatter();
        $formatter->format([$item], null, ['metadata' => ['o:id', 'dcterms:title', 'dcterms:creator']]);

        $parts = $this->readOdt((string) $formatter->getContent());
        $this->assertSame('application/vnd.oasis.opendocument.text', $parts['mimetype']);
        $this->assertNotFalse($parts['content.xml']);

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($parts['content.xml']), 'content.xml should be well-formed');
        $this->assertStringContainsString('ODT Test Item', $parts['content.xml']);
    }

    public function testFormatterHandlesEmptyList(): void
    {
        $formatter = $this->getFormatter();
        $formatter->format([]);

        $parts = $this->readOdt((string) $formatter->getContent());
        $this->assertSame('application/vnd.oasis.opendocument.text', $parts['mimetype']);
        $this->assertNotFalse($parts['content.xml']);
    }
}
