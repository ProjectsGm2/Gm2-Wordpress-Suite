<?php
use Gm2\Gm2_SEO_Public;
class SchemaOutputTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Stub WooCommerce environment if needed
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        if (!function_exists('is_product')) {
            function is_product() {
                return true;
            }
        }
        if (!class_exists('WC_Product_Stub')) {
            eval('class WC_Product_Stub {
                public function get_image_id() { return 0; }
                public function get_description() { return "Sample description"; }
                public function get_sku() { return "SKU"; }
                public function get_price() { return "10"; }
                public function is_in_stock() { return true; }
                public function get_average_rating() { return 4; }
            }');
        }
        if (!function_exists('wc_get_product')) {
            function wc_get_product($id) {
                return new WC_Product_Stub();
            }
        }
        if (!function_exists('get_woocommerce_currency')) {
            function get_woocommerce_currency() {
                return 'USD';
            }
        }
    }

    public function test_product_schema_json_ld_output() {
        register_post_type('product');
        $post_id = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'Test Product']);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        update_option('gm2_schema_product', '1');
        ob_start();
        $seo->output_product_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $output, $m);
        $json = $m[1] ?? '';
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('Product', $data['@type']);
    }

    public function test_brand_schema_json_ld_output() {
        register_taxonomy('brand', 'post');
        $term_id = self::factory()->term->create(['taxonomy' => 'brand', 'name' => 'Brand One']);
        wp_update_term($term_id, 'brand', ['description' => 'Brand description']);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_term_link($term_id, 'brand'));
        update_option('gm2_schema_brand', '1');
        ob_start();
        $seo->output_brand_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $output, $m);
        $json = $m[1] ?? '';
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('Brand', $data['@type']);
    }

    public function test_article_schema_json_ld_output() {
        $post_id = self::factory()->post->create(['post_title' => 'Sample Post']);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        update_option('gm2_schema_article', '1');
        ob_start();
        $seo->output_article_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $output, $m);
        $json = $m[1] ?? '';
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('Article', $data['@type']);
    }
}
