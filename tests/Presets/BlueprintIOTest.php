<?php

class BlueprintIOTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        delete_option('gm2_custom_posts_config');
        delete_option('gm2_field_groups');
        delete_option('gm2_cp_schema_map');
        delete_option('gm2_model_blueprint_meta');
        parent::tearDown();
    }

    public function test_full_blueprint_round_trip(): void {
        $config = [
            'post_types' => [
                'book' => [
                    'label' => 'Book',
                    'args'  => [ 'public' => [ 'value' => true ] ],
                ],
            ],
            'taxonomies' => [
                'genre' => [
                    'label'       => 'Genre',
                    'post_types'  => [ 'book' ],
                    'default_terms' => [
                        [ 'slug' => 'fiction', 'name' => 'Fiction' ],
                    ],
                ],
            ],
        ];
        update_option('gm2_custom_posts_config', $config);

        $field_groups = [
            'book_details' => [
                'title'   => 'Book Details',
                'scope'   => 'post_type',
                'objects' => [ 'book' ],
                'location'=> [],
                'fields'  => [
                    'isbn' => [
                        'label'    => 'ISBN',
                        'type'     => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ];
        update_option('gm2_field_groups', $field_groups);

        $schema_map = [
            'book' => [
                'type' => 'Book',
                'map'  => [ 'name' => 'post_title' ],
            ],
        ];
        update_option('gm2_cp_schema_map', $schema_map);

        $meta = [
            'relationships' => [
                [
                    'from' => 'book',
                    'to'   => 'genre',
                    'type' => 'taxonomy',
                ],
            ],
            'templates' => [
                'book_single' => [
                    'post_type' => 'book',
                    'blocks'    => [],
                ],
            ],
            'elementor' => [
                'queries' => [
                    'books_recent' => [
                        'id'   => 'gm2_books_recent',
                        'post_types' => [ 'book' ],
                    ],
                ],
                'templates' => [
                    'book_card' => [
                        'type' => 'shortcode',
                        'file' => 'elementor/book-card.json',
                    ],
                ],
            ],
        ];
        update_option('gm2_model_blueprint_meta', $meta);

        $export = \gm2_model_export('array');
        $this->assertIsArray($export);
        $this->assertArrayHasKey('elementor_query_ids', $export);
        $exportElementorKeys = [];
        foreach ($export['elementor_query_ids'] as $entry) {
            if (is_array($entry) && isset($entry['key'])) {
                $exportElementorKeys[] = $entry['key'];
            }
        }
        $this->assertContains('books_recent', $exportElementorKeys);

        $this->assertArrayHasKey('seo_mappings', $export);
        $exportSeoKeys = [];
        foreach ($export['seo_mappings'] as $entry) {
            if (is_array($entry) && isset($entry['key'])) {
                $exportSeoKeys[] = $entry['key'];
            }
        }
        $this->assertContains('book', $exportSeoKeys);

        delete_option('gm2_custom_posts_config');
        delete_option('gm2_field_groups');
        delete_option('gm2_cp_schema_map');
        delete_option('gm2_model_blueprint_meta');

        $result = \gm2_model_import($export, 'array');
        $this->assertTrue($result);

        $this->assertEquals($config, get_option('gm2_custom_posts_config'));
        $this->assertEquals($field_groups, get_option('gm2_field_groups'));
        $this->assertEquals($schema_map, get_option('gm2_cp_schema_map'));
        $this->assertEquals($meta, get_option('gm2_model_blueprint_meta'));
    }

    public function test_field_group_round_trip(): void {
        $field_groups = [
            'library_info' => [
                'title'   => 'Library Info',
                'scope'   => 'post_type',
                'objects' => [ 'library' ],
                'location'=> [],
                'fields'  => [
                    'address' => [ 'label' => 'Address', 'type' => 'text' ],
                ],
            ],
        ];
        update_option('gm2_field_groups', $field_groups);

        $export = \gm2_field_groups_export('array');
        $this->assertIsArray($export);
        $this->assertArrayHasKey('field_groups', $export);

        delete_option('gm2_field_groups');
        $result = \gm2_field_groups_import($export, 'array');
        $this->assertTrue($result);
        $this->assertEquals($field_groups, get_option('gm2_field_groups'));
    }

    public function test_import_validation_failure(): void {
        $invalid = [ 'post_types' => 'invalid' ];
        $result  = \gm2_model_import($invalid, 'array');
        $this->assertInstanceOf(WP_Error::class, $result);
    }
}
