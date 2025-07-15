<?php
use Gm2\Gm2_SEO_Admin;

class TaxonomyLongTailKeywordsTest extends WP_UnitTestCase {
    public function test_long_tail_keywords_saved_for_term() {
        $term_id = self::factory()->term->create(['taxonomy' => 'category']);
        $admin = new Gm2_SEO_Admin();
        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');
        $_POST['gm2_long_tail_keywords'] = 'alpha, beta ';
        $admin->save_taxonomy_meta($term_id);
        $this->assertSame('alpha, beta', get_term_meta($term_id, '_gm2_long_tail_keywords', true));
    }
}

