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

class TaxonomyContentRulesTest extends WP_Ajax_UnitTestCase {
    private function run_check($content) {
        $this->_setRole('administrator');
        $_POST['taxonomy'] = 'category';
        $_POST['content'] = $content;
        $_POST['title'] = 'Test';
        $_POST['description'] = 'Desc';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_check_rules');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_check_rules'); } catch (WPAjaxDieContinueException $e) {}
        return json_decode($this->_last_response, true);
    }

    public function test_description_word_count_rule() {
        update_option('gm2_content_rules', ['tax_category' => ['content' => 'Description has at least 150 words']]);
        $resp = $this->run_check(str_repeat('word ', 160));
        $this->assertTrue($resp['success']);
        $this->assertTrue($resp['data']['description-has-at-least-150-words']);

        $resp = $this->run_check(str_repeat('word ', 10));
        $this->assertFalse($resp['data']['description-has-at-least-150-words']);
    }
}

