<?php declare(strict_types=1);

namespace BulkExportTest\Writer;

use BulkExport\Writer\Manager as WriterManager;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Abstract base class for Writer tests.
 */
abstract class AbstractWriterTest extends AbstractHttpControllerTestCase
{
    use BulkExportTestTrait;

    /**
     * @var string
     */
    protected $writerClass;

    /**
     * @var string
     */
    protected $fileExtension;

    /**
     * @var array
     */
    protected $writerConfig = [];

    /**
     * @var array
     */
    protected $tempFiles = [];

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
     * Get a writer instance.
     */
    protected function getWriter()
    {
        $services = $this->getServiceLocator();
        $writerManager = $services->get(WriterManager::class);
        $writer = $writerManager->get($this->writerClass);

        if (!empty($this->writerConfig)) {
            $writer->setConfig($this->writerConfig);
        }

        return $writer;
    }

    /**
     * Test writer label is not empty.
     */
    public function testGetLabel(): void
    {
        $writer = $this->getWriter();
        $this->assertNotEmpty($writer->getLabel());
    }

    /**
     * Test writer extension matches expected.
     */
    public function testGetExtension(): void
    {
        $writer = $this->getWriter();
        $this->assertEquals($this->fileExtension, $writer->getExtension());
    }

    /**
     * Test writer is registered in manager.
     */
    public function testWriterIsRegistered(): void
    {
        $services = $this->getServiceLocator();
        $writerManager = $services->get(WriterManager::class);
        $this->assertTrue($writerManager->has($this->writerClass));
    }
}
