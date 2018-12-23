<?php
namespace CSVImportTest\Mvc\Controller\Plugin;

use CSVImport\Job\Import;
use CSVImportTest\Mock\Media\Ingester\MockUrl;
use Omeka\Entity\Job;
use Omeka\Stdlib\Message;
use OmekaTestHelper\Controller\OmekaControllerTestCase;

class ImportTest extends OmekaControllerTestCase
{
    protected $entityManager;
    protected $auth;
    protected $api;
    protected $basepath;

    protected $tempfile;

    public function setUp()
    {
        parent::setup();

        $this->overrideConfig();

        $services = $this->getServiceLocator();
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->auth = $services->get('Omeka\AuthenticationService');
        $this->api = $services->get('Omeka\ApiManager');
        $this->basepath = dirname(__DIR__) . '/_files/';

        $this->loginAsAdmin();

        $this->tempfile = tempnam(sys_get_temp_dir(), 'omk');
    }

    protected function overrideConfig()
    {
        require_once dirname(__DIR__) . '/Mock/Media/Ingester/MockUrl.php';

        $services = $this->getServiceLocator();

        $services->setAllowOverride(true);

        $downloader = $services->get('Omeka\File\Downloader');
        $validator = $services->get('Omeka\File\Validator');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');

        $mediaIngesterManager = $services->get('Omeka\Media\Ingester\Manager');
        $mediaIngesterManager->setAllowOverride(true);
        $mockUrl = new MockUrl($downloader, $validator);
        $mockUrl->setTempFileFactory($tempFileFactory);
        $mediaIngesterManager->setService('url', $mockUrl);
        $mediaIngesterManager->setAllowOverride(false);
    }

    public function tearDown()
    {
        if (file_exists($this->tempfile)) {
            unlink($this->tempfile);
        }
    }

    /**
     * Reset index of the all resource tables to simplify addition of tests.
     */
    protected function resetResources()
    {
        $conn = $this->getServiceLocator()->get('Omeka\Connection');
        $sql = <<<'SQL'
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE item;
TRUNCATE TABLE item_set;
TRUNCATE TABLE item_item_set;
TRUNCATE TABLE media;
TRUNCATE TABLE resource;
TRUNCATE TABLE value;
TRUNCATE TABLE csvimport_entity;
TRUNCATE TABLE csvimport_import;
SET FOREIGN_KEY_CHECKS = 1;
SQL;
        $conn->exec($sql);
        $this->entityManager->clear();
    }

    public function sourceProvider()
    {
        return [
            ['test.csv', ['items' => 3, 'media' => 4]],
            ['test_empty_rows.csv', ['items' => 3]],
            ['test_many_rows_html.tsv', ['items' => 30]],
            ['test_many_rows_url.tsv', ['items' => 30]],
            ['test_media_order.tsv', ['media' => 3], false, true],
            ['test_media_order_add.tsv', ['media' => 4], false],
            ['test_media_order_add_no_item.tsv', ['media' => 4]],
            ['test_resources.tsv', ['item_sets' => 1, 'items' => 3, 'media' => 3], false],
            ['test_resources_update.tsv', ['item_sets' => 1, 'items' => 3, 'media' => 4]],
            ['test_resources_heritage.ods', ['item_sets' => 2, 'items' => 15, 'media' => 23]],
        ];
    }

    /**
     * @dataProvider sourceProvider
     */
    public function testPerformCreate($filepath, $totals, $resetResources = true, $createItem = false)
    {
        $filepath = $this->basepath . $filepath;
        $filebase = substr($filepath, 0, -4);

        if ($createItem) {
            $item = $this->api->create('items')->getContent();
        }

        $job = $this->performProcessForFile($filepath);

        foreach ($totals as $resourceType => $total) {
            $result = $this->api->search($resourceType)->getContent();
            $this->assertEquals($total, count($result));
            foreach ($result as $key => $resource) {
                $expectedFile = $filebase . '.' . $resourceType . '-' . ($key + 1) . '.api.json';
                if (!file_exists($expectedFile)) {
                    continue;
                }
                $expected = file_get_contents($expectedFile);
                $expected = $this->cleanApiResult(json_decode($expected, true));
                $resource = $this->cleanApiResult($resource->getJsonLd());
                $this->assertNotEmpty($resource);
                $this->assertEquals($expected, $resource);
            }
        }

        if ($resetResources) {
            $this->resetResources();
        }
    }

    /**
     * This false test allows to prepare a list of resources and to use them in
     * dependencies for performance reasons.
     *
     * @return array
     */
    public function testPerformCreateOne()
    {
        $filepath = 'test.csv';
        $filepath = $this->basepath . $filepath;
        $job = $this->performProcessForFile($filepath);
        $filepath = 'test_update_g_replace.tsv';
        $filepath = $this->basepath . $filepath;
        $job = $this->performProcessForFile($filepath);
        $totals = ['item_sets' => 3, 'items' => 3, 'media' => 4];

        $this->assertTrue(true);

        $resources = [];
        foreach ($totals as $resourceType => $total) {
            $result = $this->api->search($resourceType)->getContent();
            foreach ($result as $key => $resource) {
                $resources[$resourceType][$key + 1] = $resource;
            }
        }
        return $resources;
    }

    public function sourceUpdateProvider()
    {
        return [
            ['test_skip.tsv', ['items', 1]],
            ['test_update_a_append.tsv', ['items', 1]],
            ['test_update_b_revise.tsv', ['items', 1]],
            ['test_update_c_revise.tsv', ['items', 1]],
            ['test_update_d_update.tsv', ['items', 1]],
            ['test_update_e_replace.tsv', ['items', 1]],
            ['test_update_f_replace.tsv', ['items', 1]],
            ['test_update_g_replace.tsv', ['item_sets', 1]],
            ['test_update_h_replace.tsv', ['items', 1]],
            ['test_update_i_append.tsv', ['items', 1]],
            ['test_update_j_revise.tsv', ['items', 1]],
            ['test_update_k_revise.tsv', ['items', 1]],
            ['test_update_l_update.tsv', ['items', 1]],
            ['test_update_m_update.tsv', ['items', 1]],
        ];
    }

    /**
     * @dataProvider sourceUpdateProvider
     * @depends testPerformCreateOne
     */
    public function testPerformUpdate($filepath, $options, $resources)
    {
        $filepath = $this->basepath . $filepath;
        $filebase = substr($filepath, 0, -4);
        list($resourceType, $index) = $options;

        $resource = $resources[$resourceType][$index];
        $resourceId = $resource->id();
        $resource = $this->api->read($resourceType, $resourceId)->getContent();
        $this->assertNotEmpty($resource);

        $job = $this->performProcessForFile($filepath);

        $resource = $this->api->search($resourceType, ['id' => $resourceId])->getContent();
        $this->assertNotEmpty($resource);

        $resource = reset($resource);
        $expectedFile = $filebase . '.' . $resourceType . '-' . ($index) . '.api.json';
        if (!file_exists($expectedFile)) {
            return;
        }
        $expected = file_get_contents($expectedFile);
        $expected = $this->cleanApiResult(json_decode($expected, true));
        $resource = $this->cleanApiResult($resource->getJsonLd());
        $this->assertNotEmpty($resource);
        $this->assertEquals($expected, $resource);
    }

    public function sourceDeleteProvider()
    {
        return [
            ['test_delete_items.tsv', ['items', 2]],
            ['test_delete_media.tsv', ['media', 4]],
        ];
    }

    /**
     * This test depends on other ones only to avoid check on removed resources.
     *
     * @dataProvider sourceDeleteProvider
     * @depends testPerformCreateOne
     * @depends testPerformUpdate
     */
    public function testPerformDelete($filepath, $options, $resources)
    {
        $filepath = $this->basepath . $filepath;
        $filebase = substr($filepath, 0, -4);
        list($resourceType, $index) = $options;

        $resource = $resources[$resourceType][$index];
        $resourceId = $resource->id();
        $resource = $this->api->read($resourceType, $resourceId)->getContent();
        $this->assertNotEmpty($resource);

        $job = $this->performProcessForFile($filepath);

        $resource = $this->api->search($resourceType, ['id' => $resourceId])->getContent();
        $this->assertEmpty($resource);
    }

    /**
     * Quick simple way to check import of url.
     *
     * @param string $filepath
     * @param string $basePathColumn
     * @return string
     */
    protected function addBasePath($filepath, $basePathColumn)
    {
        copy($filepath, $this->tempfile);
    }

    /**
     * Process the import of a file.
     *
     * @param string $filepath
     * @return \Omeka\Entity\Job
     */
    protected function performProcessForFile($filepath)
    {
        copy($filepath, $this->tempfile);

        $filebase = substr($filepath, 0, -4);
        $argspath = $filebase . '.args.json';
        if (!file_exists($argspath)) {
            $this->markTestSkipped(new Message('No argument files (%s).', basename($argspath))); // @translate
        }
        $args = json_decode(file_get_contents($filebase . '.args.json'), true);
        $args['filepath'] = $this->tempfile;

        $job = new Job;
        $job->setStatus(Job::STATUS_STARTING);
        $job->setClass(Import::class);
        $job->setArgs($args);
        $job->setOwner($this->auth->getIdentity());
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $import = new Import($job, $this->getServiceLocator());
        $import->perform();

        return $job;
    }

    protected function cleanApiResult(array $resource)
    {
        // Make the representation a pure array.
        $resource = json_decode(json_encode($resource), true);

        unset($resource['@context']);
        unset($resource['@type']);
        unset($resource['@id']);
        unset($resource['o:id']);
        unset($resource['o:created']);
        unset($resource['o:modified']);
        unset($resource['o:owner']['@id']);
        unset($resource['o:resource_template']['@id']);
        unset($resource['o:resource_class']['@id']);
        unset($resource['o:items']['@id']);
        unset($resource['o:sha256']);
        if (isset($resource['o:item_set'])) {
            foreach ($resource['o:item_set'] as &$itemSet) {
                unset($itemSet['@id']);
            }
        }
        if (isset($resource['o:media'])) {
            foreach ($resource['o:media'] as &$media) {
                unset($media['@id']);
            }
        }
        if (isset($resource['o:item'])) {
            unset($resource['o:item']['@id']);
            unset($resource['o:filename']);
            unset($resource['o:original_url']);
            unset($resource['o:thumbnail_urls']);
        }

        return $resource;
    }
}
