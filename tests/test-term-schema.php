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
}
