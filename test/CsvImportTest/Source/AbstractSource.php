<?php
namespace CSVImportTest\Source;

use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class AbstractSource extends OmekaControllerTestCase
{
    protected $sourceClass;

    protected $config;
    protected $basepath;

    protected $source;

    public function setUp()
    {
        parent::setup();

        $services = $this->getServiceLocator();
        $this->config = $services->get('Config');

        $this->basepath = dirname(__DIR__) . '/_files/';

        $this->loginAsAdmin();
    }

    public function tearDown()
    {
        $this->source->clean();
    }

    public function sourceProvider()
    {
        return [];
    }

    /**
     * @dataProvider sourceProvider
     */
    public function testIsValid($filepath, $options, $expected)
    {
        $source = $this->getSource($filepath, $options);
        $this->assertEquals($expected[0], $source->isValid());
    }

    /**
     * @dataProvider sourceProvider
     */
    public function testCountRows($filepath, $options, $expected)
    {
        $source = $this->getSource($filepath, $options);
        $this->assertEquals($expected[1], $source->countRows());
    }

    /**
     * @dataProvider sourceProvider
     */
    public function testGetHeaders($filepath, $options, $expected)
    {
        $source = $this->getSource($filepath, $options);
        $this->assertEquals($expected[2], $source->getHeaders());
    }

    protected function getSource($filepath, array $params = [])
    {
        $filepath = $this->basepath . $filepath;
        $tempPath = tempnam(sys_get_temp_dir(), 'omeka');
        copy($filepath, $tempPath);

        $sourceClass = $this->sourceClass;
        $source = new $sourceClass();
        $source->init($this->config);
        $source->setSource($tempPath);
        $source->setParameters($params);

        $this->source = $source;
        return $this->source;
    }
}
