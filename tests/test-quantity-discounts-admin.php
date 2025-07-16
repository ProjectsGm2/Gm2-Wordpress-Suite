<?php
use Gm2\Gm2_Quantity_Discounts_Admin;

class QuantityDiscountsAdminAjaxTest extends WP_Ajax_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        register_post_type('product');
        register_taxonomy('product_cat', 'product');
    }

    public function test_get_category_products() {
        $cat = self::factory()->term->create(['taxonomy' => 'product_cat']);
        $p1 = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'One']);
        $p2 = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'Two']);
        $p3 = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'Three']);
        update_post_meta($p1, '_sku', 'S1');
        update_post_meta($p2, '_sku', 'S2');
        update_post_meta($p3, '_sku', 'S3');
        wp_set_object_terms($p1, [$cat], 'product_cat');
        wp_set_object_terms($p2, [$cat], 'product_cat');

        $admin = new Gm2_Quantity_Discounts_Admin();
        $admin->register_hooks();

        $this->_setRole('administrator');
        $_GET['category'] = $cat;
        $_GET['nonce'] = wp_create_nonce('gm2_qd_nonce');
        try { $this->_handleAjax('gm2_qd_get_category_products'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        foreach ($resp['data'] as $item) {
            $this->assertArrayHasKey('sku', $item);
        }
        $ids = array_map(function($i){return $i['id'];}, $resp['data']);
        $this->assertContains($p1, $ids);
        $this->assertContains($p2, $ids);
        $this->assertNotContains($p3, $ids);
    }

    public function test_search_products_returns_sku() {
        $p = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'Find']);
        update_post_meta($p, '_sku', 'FSKU');

        $admin = new Gm2_Quantity_Discounts_Admin();
        $admin->register_hooks();

        $this->_setRole('administrator');
        $_GET['term'] = 'Fi';
        $_GET['nonce'] = wp_create_nonce('gm2_qd_nonce');
        try { $this->_handleAjax('gm2_qd_search_products'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $this->assertSame('FSKU', $resp['data'][0]['sku']);
    }

    public function test_save_groups_persists_label() {
        $admin = new Gm2_Quantity_Discounts_Admin();
        $admin->register_hooks();

        $this->_setRole('administrator');
        $_POST['nonce'] = wp_create_nonce('gm2_qd_nonce');
        $_POST['groups'] = [
            [
                'name'     => 'G',
                'products' => [1],
                'rules'    => [ [ 'min' => 1, 'type' => 'percent', 'amount' => 5, 'label' => 'First' ] ],
            ],
        ];
        try { $this->_handleAjax('gm2_qd_save_groups'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $this->assertTrue($resp['success']);
        $saved = get_option('gm2_quantity_discount_groups');
        $this->assertSame('First', $saved[0]['rules'][0]['label']);
    }

    public function test_search_products_excludes_drafts() {
        $published = self::factory()->post->create([
            'post_type'   => 'product',
            'post_title'  => 'Searchable',
            'post_status' => 'publish',
        ]);
        $draft = self::factory()->post->create([
            'post_type'   => 'product',
            'post_title'  => 'Searchable',
            'post_status' => 'draft',
        ]);

        $admin = new Gm2_Quantity_Discounts_Admin();
        $admin->register_hooks();

        $this->_setRole('administrator');
        $_GET['term']  = 'Searchable';
        $_GET['nonce'] = wp_create_nonce('gm2_qd_nonce');
        try { $this->_handleAjax('gm2_qd_search_products'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $ids  = array_map(function($i){return $i['id'];}, $resp['data']);
        $this->assertContains($published, $ids);
        $this->assertNotContains($draft, $ids);
    }

    public function test_get_category_products_excludes_drafts() {
        $cat = self::factory()->term->create(['taxonomy' => 'product_cat']);
        $published = self::factory()->post->create([
            'post_type'   => 'product',
            'post_title'  => 'In Cat',
            'post_status' => 'publish',
        ]);
        $draft = self::factory()->post->create([
            'post_type'   => 'product',
            'post_title'  => 'In Cat',
            'post_status' => 'draft',
        ]);
        wp_set_object_terms($published, [$cat], 'product_cat');
        wp_set_object_terms($draft, [$cat], 'product_cat');

        $admin = new Gm2_Quantity_Discounts_Admin();
        $admin->register_hooks();

        $this->_setRole('administrator');
        $_GET['category'] = $cat;
        $_GET['nonce']    = wp_create_nonce('gm2_qd_nonce');
        try { $this->_handleAjax('gm2_qd_get_category_products'); } catch (WPAjaxDieContinueException $e) {}
        $resp = json_decode($this->_last_response, true);
        $ids  = array_map(function($i){return $i['id'];}, $resp['data']);
        $this->assertContains($published, $ids);
        $this->assertNotContains($draft, $ids);
    }
}
