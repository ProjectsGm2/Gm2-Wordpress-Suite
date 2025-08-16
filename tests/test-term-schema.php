<?php
use Gm2\Gm2_SEO_Public;
class TermSchemaTest extends WP_UnitTestCase {
    public function test_generate_term_schema_data_brand() {
        $term_id = self::factory()->category->create([ 'name' => 'Widgets', 'description' => 'Great widgets' ]);
        update_term_meta($term_id, '_gm2_schema_brand', 'Acme');
        $seo = new Gm2_SEO_Public();
        $schemas = $seo->generate_term_schema_data($term_id, 'category');
        $found = false;
        foreach ($schemas as $schema) {
            if (isset($schema['@type']) && $schema['@type'] === 'Brand') {
                $found = true;
                $this->assertEquals('Acme', $schema['name']);
            }
        }
        $this->assertTrue($found);
    }
    public function test_generate_term_schema_data_rating() {
        $term_id = self::factory()->category->create([ 'name' => 'Gadgets', 'description' => 'Great gadgets' ]);
        update_term_meta($term_id, '_gm2_schema_rating', '4.5');
        $seo = new Gm2_SEO_Public();
        $schemas = $seo->generate_term_schema_data($term_id, 'category');
        $found = false;
        foreach ($schemas as $schema) {
            if (isset($schema['@type']) && $schema['@type'] === 'Review') {
                $found = true;
                $this->assertEquals('4.5', $schema['reviewRating']['ratingValue']);
            }
        }
        $this->assertTrue($found);
    }

    public function test_term_meta_field_exposed_in_rest_schema() {
        update_option('gm2_custom_posts_config', [
            'post_types' => [],
            'taxonomies' => [
                'genre' => [
                    'label' => 'Genre',
                    'post_types' => ['post'],
                    'args' => [
                        'show_in_rest' => [ 'value' => true ],
                    ],
                    'default_terms' => [
                        [
                            'slug' => 'horror',
                            'name' => 'Horror',
                            'meta' => [ 'rating' => '5' ],
                        ],
                    ],
                    'term_fields' => [
                        'rating' => [ 'type' => 'number', 'description' => 'Rating' ],
                    ],
                ],
            ],
        ]);

        gm2_register_custom_posts();

        $request  = new WP_REST_Request('OPTIONS', '/wp/v2/genre');
        $response = rest_get_server()->dispatch($request);
        $schema   = $response->get_data();
        $this->assertArrayHasKey('meta', $schema['schema']['properties']);
        $this->assertArrayHasKey('rating', $schema['schema']['properties']['meta']['properties']);

        $get = new WP_REST_Request('GET', '/wp/v2/genre');
        $get->set_param('_fields', 'slug,meta');
        $resp = rest_get_server()->dispatch($get);
        $data = $resp->get_data();
        $found = false;
        foreach ($data as $term) {
            if ($term['slug'] === 'horror') {
                $found = true;
                $this->assertSame('5', $term['meta']['rating']);
            }
        }
        $this->assertTrue($found);

        unregister_taxonomy('genre');
        delete_option('gm2_custom_posts_config');
    }
}
