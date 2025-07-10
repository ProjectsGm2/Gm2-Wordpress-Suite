<?php
use Gm2\Gm2_SEO_Admin;

class TaxonomyPermissionsTest extends WP_UnitTestCase {
    public function test_save_taxonomy_meta_without_permission_does_nothing() {
        $term_id = self::factory()->term->create(['taxonomy' => 'category']);
        $user_id = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $admin = new Gm2_SEO_Admin();

        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');
        $_POST['gm2_seo_title'] = 'Title';

        $admin->save_taxonomy_meta($term_id);

        wp_set_current_user(0);
        $_POST = [];

        $this->assertFalse(metadata_exists('term', $term_id, '_gm2_title'));
    }
}

