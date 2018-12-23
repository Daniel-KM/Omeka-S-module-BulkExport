<?php
namespace BulkImportTest\Mvc\Controller\Plugin;

use BulkImport\Mvc\Controller\Plugin\FindResourcesFromIdentifiers;
use OmekaTestHelper\Controller\OmekaControllerTestCase;

class FindResourcesFromIdentifiersTest extends OmekaControllerTestCase
{
    protected $connection;
    protected $api;
    protected $findResourcesFromIdentifier;

    protected $resources;

    public function setUp()
    {
        parent::setup();

        $services = $this->getServiceLocator();
        $this->connection = $services->get('Omeka\Connection');
        $this->api = $services->get('Omeka\ApiManager');
        $this->findResourcesFromIdentifier = new FindResourcesFromIdentifiers($this->connection, $this->api);

        $this->loginAsAdmin();

        // 10 is property id of dcterms:identifier.
        $this->resources[] = $this->api->create('item_sets', [
            'dcterms:identifier' => [
                ['property_id' => 10, 'type' => 'literal', '@value' => 'Foo Item Set'],
            ],
        ])->getContent();

        $this->resources[] = $this->api->create('items', [
            'dcterms:identifier' => [
                ['property_id' => 10, 'type' => 'literal', '@value' => 'Foo Item'],
            ],
        ])->getContent();

        $this->resources[] = $this->api->create('media', [
            'dcterms:identifier' => [
                ['property_id' => 10, 'type' => 'literal', '@value' => 'Foo Media'],
            ],
            'o:ingester' => 'html',
            'html' => '<p>This <strong>is</strong> <em>html</em>.</p>',
            'o:item' => ['o:id' => $this->resources[1]->id()],
        ])->getContent();

        // Check a case insensitive duplicate (should return the first).
        $this->resources[] = $this->api->create('item_sets', [
            'dcterms:identifier' => [
                ['property_id' => 10, 'type' => 'literal', '@value' => 'foo item set'],
            ],
        ])->getContent();

        // Allows to check a true duplicate (should return the first).
        $this->resources[] = $this->api->create('items', [
            'dcterms:identifier' => [
                ['property_id' => 10, 'type' => 'literal', '@value' => 'Foo Item'],
            ],
        ])->getContent();
    }

    public function tearDown()
    {
        $map = [
            'o:ItemSet' => 'item_sets',
            'o:Item' => 'items',
            'o:Media' => 'media',
        ];
        foreach ($this->resources as $resource) {
            $resourceType = $resource->getResourceJsonLdType();
            if ($resourceType === 'o:Media') {
                continue;
            }
            $this->api()->delete($map[$resourceType], $resource->id());
        }
        $this->resources = [];
    }

    public function testNoIdentifier()
    {
        $findResourcesFromIdentifier = $this->findResourcesFromIdentifier;
        $item = $this->api->create('items', [])->getContent();

        $identifierProperty = 'internal_id';
        $resourceType = null;

        $identifier = '';
        $resource = $findResourcesFromIdentifier($identifier, $identifierProperty, $resourceType);
        $this->assertNull($resource);

        $identifiers = [];
        $resources = $findResourcesFromIdentifier($identifiers, $identifierProperty, $resourceType);
        $this->assertTrue(is_array($resources));
        $this->assertEmpty($resources);
    }

    /**
     * @dataProvider resourceIdentifierProvider
     */
    public function testResourceIdentifier($identifier, $identifierProperty, $resourceType, $expected)
    {
        $expected = is_null($expected) ? null : $this->resources[$expected]->id();

        $findResourcesFromIdentifier = $this->findResourcesFromIdentifier;

        $resource = $findResourcesFromIdentifier($identifier, $identifierProperty, $resourceType);
        $this->assertEquals($expected, $resource);

        $resources = $findResourcesFromIdentifier([$identifier], $identifierProperty, $resourceType);
        $this->assertEquals(1, count($resources));
        $this->assertEquals($expected, $resources[$identifier]);
    }

    public function resourceIdentifierProvider()
    {
        return [
            ['Foo Item Set', 10, 'item_sets', 0],
            ['Foo Item', 10, 'items', 1],
            ['Foo Media', 10, 'media', 2],
            ['foo item set', 10, 'item_sets', 3],
            ['unknown', 10, 'item_sets', null],
            ['unknown', 10, 'items', null],
            ['unknown', 10, 'media', null],
        ];
    }

    /**
     * @dataProvider resourceIdentifiersProvider
     */
    public function testResourceIdentifiers($identifiers, $identifierProperty, $resourceType, $expecteds)
    {
        foreach ($expecteds as &$expected) {
            $expected = is_null($expected) ? null : $this->resources[$expected]->id();
        }

        $findResourcesFromIdentifier = $this->findResourcesFromIdentifier;

        $resources = $findResourcesFromIdentifier($identifiers, $identifierProperty, $resourceType);
        $this->assertEquals(count(array_unique($identifiers)), count($resources));
        $this->assertEquals($expecteds, array_values($resources));
    }

    public function resourceIdentifiersProvider()
    {
        return [
            [['Foo Item Set'], 10, 'item_sets', [0]],
            [['Foo Item Set', 'foo item set'], 10, 'item_sets', [0, 3]],
            [['foo item set', 'Foo Item Set'], 10, 'item_sets', [3, 0]],
            [['foo item set', 'Foo Item Set', 'foo item set'], 10, 'item_sets', [3, 0]],
            [['foo item set', 'unknown', 'Foo Item Set', 'foo item set'], 10, 'item_sets', [3, null, 0]],
        ];
    }
}
