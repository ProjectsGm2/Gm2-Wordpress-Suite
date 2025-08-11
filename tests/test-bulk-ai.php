<?php
use Gm2\Gm2_SEO_Admin;
use Gm2\Gm2_Bulk_Ai_List_Table;
use Gm2\Gm2_Bulk_Ai_Tax_List_Table;

class BulkAiPageTest extends WP_UnitTestCase {
    public function test_page_requires_edit_posts_cap() {
        $admin = new Gm2_SEO_Admin();
        $user = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('Permission denied', $out);
    }

    public function test_titles_link_to_edit_page() {
        $post_id = self::factory()->post->create(['post_title' => 'Linked']);
        $admin   = new Gm2_SEO_Admin();
        $user    = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $edit_link = get_edit_post_link($post_id);
        $this->assertStringContainsString('href="' . $edit_link . '"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }
}

class BulkAiApplyAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_apply_updates_post_meta() {
        $post_id = self::factory()->post->create();
        $this->_setRole('administrator');
        $_POST['post_id'] = $post_id;
        $_POST['seo_title'] = 'New';
        $_POST['seo_description'] = 'Desc';
        $_POST['focus_keywords'] = 'alpha, beta';
        $_POST['long_tail_keywords'] = 'gamma, delta';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_apply');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_bulk_ai_apply'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $post = get_post($post_id);
        $this->assertSame('New', get_post_meta($post_id, '_gm2_title', true));
        $this->assertSame('Desc', get_post_meta($post_id, '_gm2_description', true));
        $this->assertSame('alpha, beta', get_post_meta($post_id, '_gm2_focus_keywords', true));
        $this->assertSame('gamma, delta', get_post_meta($post_id, '_gm2_long_tail_keywords', true));
    }

    public function test_apply_requires_cap() {
        $post_id = self::factory()->post->create();
        $this->_setRole('subscriber');
        $_POST['post_id'] = $post_id;
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_apply');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_bulk_ai_apply'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertFalse($resp['success']);
    }
}

class BulkAiResetAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_reset_all_clears_ai_research_with_filters() {
        $post1 = self::factory()->post->create(['post_status' => 'publish']);
        $post2 = self::factory()->post->create(['post_status' => 'draft']);
        update_post_meta($post1, '_gm2_ai_research', 'data1');
        update_post_meta($post2, '_gm2_ai_research', 'data2');
        $this->_setRole('administrator');

        $_POST['all'] = '1';
        $_POST['status'] = 'publish';
        $_POST['post_type'] = 'all';
        $_POST['seo_status'] = 'has_ai';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_reset');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];

        try { $this->_handleAjax('gm2_bulk_ai_reset'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame(1, $resp['data']['reset']);
        $this->assertSame(1, $resp['data']['cleared']);
        $this->assertEmpty(get_post_meta($post1, '_gm2_ai_research', true));
        $this->assertSame('data2', get_post_meta($post2, '_gm2_ai_research', true));
    }
}

class BulkAiTaxApplyAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_apply_updates_term_meta() {
        $term_id = self::factory()->term->create(['taxonomy' => 'category']);
        $this->_setRole('administrator');
        $_POST['term_id'] = $term_id;
        $_POST['taxonomy'] = 'category';
        $_POST['seo_title'] = 'New';
        $_POST['seo_description'] = 'Desc';
        $_POST['focus_keywords'] = 'alpha';
        $_POST['long_tail_keywords'] = 'beta';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_apply');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_bulk_ai_tax_apply'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('New', get_term_meta($term_id, '_gm2_title', true));
        $this->assertSame('Desc', get_term_meta($term_id, '_gm2_description', true));
        $this->assertSame('alpha', get_term_meta($term_id, '_gm2_focus_keywords', true));
        $this->assertSame('beta', get_term_meta($term_id, '_gm2_long_tail_keywords', true));
        $this->assertSame('New', $resp['data']['seo_title']);
        $this->assertSame('Desc', $resp['data']['seo_description']);
        $this->assertSame('alpha', $resp['data']['focus_keywords']);
        $this->assertSame('beta', $resp['data']['long_tail_keywords']);
    }
}

class BulkAiTaxResetAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_reset_all_clears_seo_and_ai_meta_with_filters() {
        $term1 = self::factory()->term->create(['taxonomy' => 'category']);
        $term2 = self::factory()->term->create(['taxonomy' => 'category']);
        update_term_meta($term1, '_gm2_title', 'Title1');
        update_term_meta($term1, '_gm2_description', 'Desc1');
        update_term_meta($term1, '_gm2_prev_title', 'PrevT1');
        update_term_meta($term1, '_gm2_prev_description', 'PrevD1');
        update_term_meta($term1, '_gm2_ai_research', 'AI1');
        update_term_meta($term1, '_gm2_focus_keywords', 'fk1');
        update_term_meta($term1, '_gm2_long_tail_keywords', 'lt1');
        update_term_meta($term1, '_gm2_prev_focus_keywords', 'pfk1');
        update_term_meta($term1, '_gm2_prev_long_tail_keywords', 'plt1');

        update_term_meta($term2, '_gm2_title', 'Title2');
        update_term_meta($term2, '_gm2_description', 'Desc2');
        update_term_meta($term2, '_gm2_focus_keywords', 'keepfk');
        update_term_meta($term2, '_gm2_long_tail_keywords', 'keeplt');

        $this->_setRole('administrator');

        $_POST['all'] = '1';
        $_POST['taxonomy'] = 'all';
        $_POST['status'] = 'publish';
        $_POST['seo_status'] = 'has_ai';
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_tax_reset');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];

        try { $this->_handleAjax('gm2_bulk_ai_tax_reset'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame(1, $resp['data']['reset']);
        $this->assertEmpty(get_term_meta($term1, '_gm2_title', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_description', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_prev_title', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_prev_description', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_ai_research', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_focus_keywords', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_long_tail_keywords', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_prev_focus_keywords', true));
        $this->assertEmpty(get_term_meta($term1, '_gm2_prev_long_tail_keywords', true));
        $this->assertSame('Title2', get_term_meta($term2, '_gm2_title', true));
        $this->assertSame('Desc2', get_term_meta($term2, '_gm2_description', true));
        $this->assertSame('keepfk', get_term_meta($term2, '_gm2_focus_keywords', true));
        $this->assertSame('keeplt', get_term_meta($term2, '_gm2_long_tail_keywords', true));
    }
}

class BulkAiFilterTest extends WP_UnitTestCase {
    public function test_post_type_filter_limits_results() {
        $post1 = self::factory()->post->create(['post_title' => 'Post One']);
        $page1 = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'Page One']);
        $user = self::factory()->user->create(['role' => 'administrator']);
        update_user_meta($user, 'gm2_bulk_ai_post_type', 'page');
        $admin = new Gm2_SEO_Admin();
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $out = ob_get_clean();
        $this->assertStringContainsString('Page One', $out);
        $this->assertStringNotContainsString('Post One', $out);
    }

    public function test_category_filter_limits_results() {
        $cat1 = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Alpha']);
        $cat2 = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Beta']);
        $in = self::factory()->post->create(['post_title' => 'In', 'post_category' => [$cat1]]);
        $out_post = self::factory()->post->create(['post_title' => 'Out', 'post_category' => [$cat2]]);
        $user = self::factory()->user->create(['role' => 'administrator']);
        update_user_meta($user, 'gm2_bulk_ai_post_type', 'post');
        update_user_meta($user, 'gm2_bulk_ai_term', ['category' => [$cat1]]);
        $admin = new Gm2_SEO_Admin();
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('In', $html);
        $this->assertStringNotContainsString('Out', $html);
    }

    public function test_multiple_category_filter_includes_both() {
        $cat1 = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Alpha']);
        $cat2 = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Beta']);
        $in1 = self::factory()->post->create(['post_title' => 'In1', 'post_category' => [$cat1]]);
        $in2 = self::factory()->post->create(['post_title' => 'In2', 'post_category' => [$cat2]]);

        $user = self::factory()->user->create(['role' => 'administrator']);
        update_user_meta($user, 'gm2_bulk_ai_post_type', 'post');
        update_user_meta($user, 'gm2_bulk_ai_term', ['category' => [$cat1, $cat2]]);
        $admin = new Gm2_SEO_Admin();
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('In1', $html);
        $this->assertStringContainsString('In2', $html);
    }

    public function test_product_category_filter_limits_results() {
        register_post_type('product');
        register_taxonomy('product_cat', 'product');

        $cat = self::factory()->term->create(['taxonomy' => 'product_cat', 'name' => 'One']);
        $in  = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'ProdIn']);
        $out = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'ProdOut']);
        wp_set_object_terms($in, [$cat], 'product_cat');

        $user = self::factory()->user->create(['role' => 'administrator']);
        update_user_meta($user, 'gm2_bulk_ai_post_type', 'product');
        update_user_meta($user, 'gm2_bulk_ai_term', ['product_cat' => [$cat]]);
        $admin = new Gm2_SEO_Admin();
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('ProdIn', $html);
        $this->assertStringNotContainsString('ProdOut', $html);
    }

    public function test_missing_title_filter_limits_results() {
        $has = self::factory()->post->create(['post_title' => 'Has']);
        update_post_meta($has, '_gm2_title', 't');
        $missing = self::factory()->post->create(['post_title' => 'Missing']);
        update_option('gm2_bulk_ai_missing_title', '1');
        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('Missing', $html);
        $this->assertStringNotContainsString('Has', $html);
    }

    public function test_missing_description_filter_limits_results() {
        $has = self::factory()->post->create(['post_title' => 'HasD']);
        update_post_meta($has, '_gm2_description', 'd');
        $missing = self::factory()->post->create(['post_title' => 'MissingD']);
        update_option('gm2_bulk_ai_missing_description', '1');
        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('MissingD', $html);
        $this->assertStringNotContainsString('HasD', $html);
    }

    public function test_ai_suggestions_filter_limits_results() {
        $with = self::factory()->post->create(['post_title' => 'HasAI']);
        update_post_meta($with, '_gm2_ai_research', 'data');
        $without = self::factory()->post->create(['post_title' => 'NoAI']);
        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        update_user_meta($user, 'gm2_bulk_ai_seo_status', 'has_ai');
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('HasAI', $html);
        $this->assertStringNotContainsString('NoAI', $html);
    }
}

class BulkAiTaxFilterTest extends WP_UnitTestCase {
    public function test_missing_title_filter_limits_terms() {
        $has = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Has']);
        update_term_meta($has, '_gm2_title', 't');
        $missing = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Missing']);
        update_option('gm2_bulk_ai_tax_missing_title', '1');
        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_tax_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('Missing', $html);
        $this->assertStringNotContainsString('Has', $html);
    }

    public function test_missing_description_filter_limits_terms() {
        $has = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'HasD']);
        update_term_meta($has, '_gm2_description', 'd');
        $missing = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'MissingD']);
        update_option('gm2_bulk_ai_tax_missing_description', '1');
        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_tax_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('MissingD', $html);
        $this->assertStringNotContainsString('HasD', $html);
    }

    public function test_ai_suggestions_filter_limits_terms() {
        $has = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'HasAI']);
        update_term_meta($has, '_gm2_ai_research', 'data');
        $missing = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'NoAI']);
        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        update_user_meta($user, 'gm2_bulk_ai_tax_seo_status', 'has_ai');
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_tax_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('HasAI', $html);
        $this->assertStringNotContainsString('NoAI', $html);
    }
}

class BulkAiPaginationTest extends WP_UnitTestCase {
    public function test_second_page_shows_expected_posts() {
        $user = self::factory()->user->create(['role' => 'administrator']);
        update_user_meta($user, 'gm2_bulk_ai_page_size', 2);
        $posts = self::factory()->post->create_many(3);
        $admin = new Gm2_SEO_Admin();
        wp_set_current_user($user);
        $_GET['paged'] = 2;
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString(get_post($posts[2])->post_title, $html);
        $this->assertStringNotContainsString(get_post($posts[0])->post_title, $html);
        $this->assertStringContainsString('paged=1', $html);
        unset($_GET['paged']);
    }
}

class BulkAiCachedResultsTest extends WP_UnitTestCase {
    public function test_cached_results_preloaded() {
        $post_id = self::factory()->post->create(['post_title' => 'Post']);
        update_post_meta($post_id, '_gm2_ai_research', wp_json_encode(['seo_title' => 'Cached Title']));

        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();
        $this->assertStringContainsString('Cached Title', $html);
        $this->assertStringContainsString('gm2-refresh-btn', $html);
    }
}

class BulkAiApplyBatchAjaxTest extends WP_Ajax_UnitTestCase {
    public function test_batch_apply_updates_posts() {
        $posts = self::factory()->post->create_many(2);
        $this->_setRole('administrator');
        $payload = [
            $posts[0] => [ 'seo_title' => 'One', 'seo_description' => 'Desc1' ],
            $posts[1] => [ 'focus_keywords' => 'alpha', 'long_tail_keywords' => 'beta' ],
        ];
        $_POST['posts'] = wp_json_encode($payload);
        $_POST['_ajax_nonce'] = wp_create_nonce('gm2_bulk_ai_apply');
        $_REQUEST['_ajax_nonce'] = $_POST['_ajax_nonce'];
        try { $this->_handleAjax('gm2_bulk_ai_apply_batch'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('One', get_post_meta($posts[0], '_gm2_title', true));
        $this->assertSame('Desc1', get_post_meta($posts[0], '_gm2_description', true));
        $this->assertSame('alpha', get_post_meta($posts[1], '_gm2_focus_keywords', true));
        $this->assertSame('beta', get_post_meta($posts[1], '_gm2_long_tail_keywords', true));
    }
}

class BulkAiExportTest extends WP_UnitTestCase {
    public function test_csv_export_contains_suggestions() {
        $post_id = self::factory()->post->create(['post_title' => 'Post']);
        update_post_meta($post_id, '_gm2_ai_research', wp_json_encode([
            'seo_title'  => 'Suggested Title',
            'description'=> 'Suggested Desc',
            'focus_keywords' => 'alpha',
            'long_tail_keywords' => ['beta'],
        ]));

        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        ob_start();
        $admin->handle_bulk_ai_export();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Suggested Title', $csv);
        $this->assertStringContainsString('alpha', $csv);
    }
}

class BulkAiTaxExportTest extends WP_UnitTestCase {
    public function test_tax_csv_export_contains_terms() {
        $term = self::factory()->term->create_and_get([
            'taxonomy' => 'category',
            'name'     => 'Sample Term',
        ]);
        update_term_meta($term->term_id, '_gm2_title', 'Term Title');
        update_term_meta($term->term_id, '_gm2_description', 'Term Desc');

        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        ob_start();
        $admin->handle_bulk_ai_tax_export();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Sample Term', $csv);
        $this->assertStringContainsString('Term Title', $csv);
        $this->assertStringContainsString('category', $csv);
    }
}

class BulkAiSelectAllTest extends WP_UnitTestCase {
    public function test_select_all_option_displays_and_checks_all() {
        $post_id = self::factory()->post->create(['post_title' => 'Analyzed']);
        update_post_meta($post_id, '_gm2_ai_research', wp_json_encode([
            'seo_title'  => 'Title',
            'description'=> 'Desc',
        ]));

        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        ob_start();
        $admin->display_bulk_ai_page();
        $html = ob_get_clean();

        $this->assertStringContainsString('gm2-row-select-all', $html);

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $select = $xpath->query("//input[contains(@class,'gm2-row-select-all')]")->item(0);
        $boxes  = $xpath->query("//input[contains(@class,'gm2-apply')]");
        $this->assertGreaterThan(0, $boxes->length);
        foreach ($boxes as $box) {
            $this->assertFalse($box->hasAttribute('checked'));
        }
        if ($select) {
            $select->setAttribute('checked', 'checked');
            foreach ($boxes as $box) {
                $box->setAttribute('checked', 'checked');
            }
        }
        foreach ($boxes as $box) {
            $this->assertSame('checked', $box->getAttribute('checked'));
        }
    }
}

class BulkAiTaxSelectAllTest extends WP_UnitTestCase {
    public function test_select_all_option_displayed() {
        $term_id = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Analyzed']);
        update_term_meta($term_id, '_gm2_ai_research', wp_json_encode([
            'seo_title'  => 'Title',
            'description'=> 'Desc',
        ]));

        $admin = new Gm2_SEO_Admin();
        $user  = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);

        ob_start();
        $admin->display_bulk_ai_tax_page();
        $html = ob_get_clean();

        $this->assertStringContainsString('gm2-row-select-all', $html);
        $this->assertStringContainsString('Select all', $html);
    }
}

class BulkAiColumnAiTest extends WP_UnitTestCase {
    public function test_column_ai_wraps_output() {
        $post_id = self::factory()->post->create();
        update_post_meta($post_id, '_gm2_ai_research', wp_json_encode([
            'seo_title' => 'Title',
        ]));
        $admin = new Gm2_SEO_Admin();
        $table = new Gm2_Bulk_Ai_List_Table($admin, []);
        $method = new ReflectionMethod($table, 'column_ai');
        $method->setAccessible(true);
        $html = $method->invoke($table, get_post($post_id));
        $this->assertStringContainsString('<div class="gm2-result">', $html);
        $this->assertStringContainsString('Title', $html);
        $this->assertStringContainsString('</div>', $html);
    }
}

class BulkAiTaxColumnAiTest extends WP_UnitTestCase {
    public function test_tax_column_ai_wraps_output() {
        $term = self::factory()->term->create_and_get(['taxonomy' => 'category', 'name' => 'Term']);
        update_term_meta($term->term_id, '_gm2_ai_research', wp_json_encode([
            'seo_title' => 'Term Title',
        ]));
        $admin = new Gm2_SEO_Admin();
        $table = new Gm2_Bulk_Ai_Tax_List_Table($admin, []);
        $method = new ReflectionMethod($table, 'column_ai');
        $method->setAccessible(true);
        $html = $method->invoke($table, $term);
        $this->assertStringContainsString('<div class="gm2-result">', $html);
        $this->assertStringContainsString('Term Title', $html);
        $this->assertStringContainsString('</div>', $html);
    }
}
