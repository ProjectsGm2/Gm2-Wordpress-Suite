<?php

use Gm2\Fields\FieldGroupRegistry;
use Gm2\Fields\FieldTypeRegistry;
use Gm2\Fields\Renderer\AdminMetaBox;
use Gm2\Fields\Sanitizers\SanitizerRegistry;
use Gm2\Fields\Storage\MetaRegistrar;
use Gm2\Fields\Validation\ValidatorRegistry;

class FieldGroupRegistryTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        register_post_type('library_book', [
            'show_in_rest' => true,
            'supports'     => [ 'custom-fields' ],
        ]);
    }

    protected function tearDown(): void
    {
        unregister_post_type('library_book');
        parent::tearDown();
    }

    public function test_registers_meta_schema_and_dependencies(): void
    {
        $registry = $this->createRegistry();

        $registry->registerGroup('library_book_details', [
            'title'    => 'Library Book Details',
            'contexts' => [
                'post' => [ 'library_book' ],
            ],
            'fields'   => [
                'has_isbn' => [
                    'type'    => 'switch',
                    'label'   => 'Has ISBN',
                    'default' => false,
                ],
                'isbn' => [
                    'type'       => 'text',
                    'label'      => 'ISBN',
                    'conditions' => [
                        'relation' => 'and',
                        [
                            'field'    => 'has_isbn',
                            'operator' => '==',
                            'value'    => true,
                        ],
                    ],
                ],
                'price' => [
                    'type'  => 'number',
                    'label' => 'Price',
                ],
                'tax' => [
                    'type'  => 'number',
                    'label' => 'Tax',
                ],
                'total' => [
                    'type'     => 'computed',
                    'computed' => [
                        'dependencies' => [ 'price', 'tax' ],
                    ],
                ],
            ],
        ]);

        $registry->boot();

        $registered = get_registered_meta_keys('post', 'library_book');
        $this->assertArrayHasKey('isbn', $registered);
        $this->assertSame('string', $registered['isbn']['type']);
        $this->assertArrayHasKey('schema', $registered['isbn']['show_in_rest']);

        $graph = $registry->getComputedDependencyGraph('library_book_details');
        $this->assertSame([
            'total' => [ 'price', 'tax' ],
        ], $graph);
    }

    private function createRegistry(): FieldGroupRegistry
    {
        return new FieldGroupRegistry(
            new MetaRegistrar(),
            FieldTypeRegistry::withDefaults(),
            ValidatorRegistry::withDefaults(),
            SanitizerRegistry::withDefaults(),
            new AdminMetaBox()
        );
    }
}
