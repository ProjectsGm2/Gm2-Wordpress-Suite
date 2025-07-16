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
}
