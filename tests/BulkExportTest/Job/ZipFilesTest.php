<?php declare(strict_types=1);

namespace BulkExportTest\Job;

use BulkExport\Job\Export;
use BulkExportTest\BulkExportTestTrait;
use Omeka\Entity\Job;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Names of the files in the zip of media files.
 *
 * @see https://gitlab.com/Daniel-KM/Omeka-S-module-BulkExport/-/work_items/17
 */
class ZipFilesTest extends AbstractHttpControllerTestCase
{
    use BulkExportTestTrait;

    /**
     * @var string[]
     */
    protected $tempFiles = [];

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('Requires php extension zip.');
        }
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function basePath(): string
    {
        $config = $this->getServiceLocator()->get('Config');
        return $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    }

    /**
     * Create an item with two media backed by fake files (tiff original and
     * jpeg thumbnail, as stored by Omeka).
     */
    protected function createItemWithFiles(): array
    {
        $html = fn (string $text): array => ['o:ingester' => 'html', 'html' => "<p>$text</p>"];
        $item = $this->createItem([
            'dcterms:title' => [['type' => 'literal', '@value' => 'Zip']],
            'o:media' => [$html('a'), $html('b')],
        ]);
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $basePath = $this->basePath();
        $mediaIds = [];
        foreach ($item->media() as $media) {
            $storageId = 'bulkexport_test_' . $media->id() . '_' . bin2hex(random_bytes(4));
            $connection->executeStatement(
                'UPDATE `media` SET `storage_id` = ?, `extension` = ?, `has_original` = 1, `has_thumbnails` = 1 WHERE `id` = ?',
                [$storageId, 'tif', $media->id()]
            );
            foreach (['original/' . $storageId . '.tif', 'large/' . $storageId . '.jpg'] as $path) {
                $filepath = $basePath . '/' . $path;
                @mkdir(dirname($filepath), 0775, true);
                file_put_contents($filepath, $path);
                $this->tempFiles[] = $filepath;
            }
            $mediaIds[] = $media->id();
        }
        return [$item->id(), $mediaIds];
    }

    protected function zipEntries(string $format, int $jobId): array
    {
        $filepath = $this->basePath() . '/temp/export_' . $format . '_' . $jobId . '_0001.zip';
        $this->assertFileExists($filepath);
        $this->tempFiles[] = $filepath;
        $zip = new \ZipArchive();
        $zip->open($filepath);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($entries);
        return $entries;
    }

    /**
     * @group integration
     */
    public function testZipFilesByItemFolder(): void
    {
        [$itemId, $mediaIds] = $this->createItemWithFiles();

        $exporter = $this->createExporter('Zip Test', 'csv', $this->getCsvFormatterConfig() + [
            'zip_files' => ['original', 'large'],
            'zip_files_naming' => 'item_folder',
        ]);
        $export = $this->createExport($exporter, [
            'formatter' => [
                'resource_types' => ['items'],
                'query' => ['id' => [$itemId]],
            ],
        ]);

        $job = $this->runJob(Export::class, ['bulk_export_id' => $export->getId()]);
        $this->assertEquals(Job::STATUS_COMPLETED, $job->getStatus());

        $expected = fn (string $dir, string $extension): array => array_map(
            fn ($id) => "$dir/$itemId/$id.$extension",
            $mediaIds
        );
        $this->assertSame($expected('original', 'tif'), $this->zipEntries('original', $job->getId()));
        // Thumbnails are stored as jpeg, whatever the original format.
        $this->assertSame($expected('large', 'jpg'), $this->zipEntries('large', $job->getId()));
    }

    public function testZipEntryNames(): void
    {
        $job = (new \ReflectionClass(Export::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Export::class, 'zipEntryName');
        $method->setAccessible(true);

        $media = ['file' => 'original/abc.tif', 'item_id' => 3, 'media_id' => 7];
        $asset = ['file' => 'asset/def.png', 'item_id' => 3, 'media_id' => null];

        $this->assertSame('original/abc.tif', $method->invoke($job, $media, 'storage'));
        $this->assertSame('original/3/7.tif', $method->invoke($job, $media, 'item_folder'));
        $this->assertSame('original/3_7.tif', $method->invoke($job, $media, 'item_prefix'));
        $this->assertSame('asset/3/asset.png', $method->invoke($job, $asset, 'item_folder'));
        $this->assertSame('asset/3_asset.png', $method->invoke($job, $asset, 'item_prefix'));
    }
}
