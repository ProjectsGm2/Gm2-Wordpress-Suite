<?php
use Gm2\Gm2_SEO_Public;

class SchemaPlaceholderTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        if (!class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
        if (!function_exists('is_product')) {
            function is_product() { return true; }
        }
        if (!class_exists('WC_Product_Stub')) {
            eval('class WC_Product_Stub {
                public function get_image_id() { return 0; }
                public function get_description() { return "Sample description"; }
                public function get_sku() { return "SKU"; }
                public function get_price() { return "10"; }
                public function is_in_stock() { return true; }
            }');
        }
        if (!function_exists('wc_get_product')) {
            function wc_get_product($id) { return new WC_Product_Stub(); }
        }
        if (!function_exists('get_woocommerce_currency')) {
            function get_woocommerce_currency() { return 'USD'; }
        }
    }

    public function test_default_product_template_placeholders() {
        register_post_type('product');
        $post_id = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'Placeholder Product']);
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        update_option('gm2_schema_product', '1');
        ob_start();
        $seo->output_product_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1], true);
        $this->assertSame('Placeholder Product', $data['name']);
        $this->assertSame(get_permalink($post_id), $data['offers']['url']);
    }

    public function test_custom_product_template_placeholders() {
        register_post_type('product');
        $post_id = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'Custom Product']);
        $tpl = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => '{{title}}',
            'sku' => 'SKU-{{sku}}',
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => '{{price_currency}}',
                'price' => '{{price}}',
                'url' => '{{permalink}}',
            ],
        ];
        update_option('gm2_schema_template_product', wp_json_encode($tpl));
        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        update_option('gm2_schema_product', '1');
        ob_start();
        $seo->output_product_schema();
        $output = ob_get_clean();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1], true);
        $this->assertSame('Custom Product', $data['name']);
        $this->assertSame('SKU-SKU', $data['sku']);
        $this->assertSame('10', $data['offers']['price']);
        $this->assertSame(get_permalink($post_id), $data['offers']['url']);
    }

    public function test_dynamic_tokens_replace_taxonomy_field_and_location() {
        register_post_type('product');
        register_taxonomy('product_cat', 'product');
        $post_id = self::factory()->post->create(['post_type' => 'product', 'post_title' => 'Token Product']);
        $term_id = self::factory()->term->create(['taxonomy' => 'product_cat', 'name' => 'Accessories']);
        wp_set_post_terms($post_id, [$term_id], 'product_cat');
        update_post_meta($post_id, 'custom_field', 'Custom Value');
        update_post_meta($post_id, 'location_city', 'Seattle');

        $tpl = [
            '@context'      => 'https://schema.org/',
            '@type'         => 'Product',
            'name'          => '{{title}}',
            'category'      => '{taxonomy:product_cat}',
            'customField'   => '{field:custom_field}',
            'locationCity'  => '{location_city}',
        ];
        update_option('gm2_schema_template_product', wp_json_encode($tpl));

        $seo = new Gm2_SEO_Public();
        $this->go_to(get_permalink($post_id));
        setup_postdata(get_post($post_id));
        update_option('gm2_schema_product', '1');

        ob_start();
        $seo->output_product_schema();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/', $output, $m);
        $data = json_decode($m[1], true);

        $this->assertSame('Accessories', $data['category']);
        $this->assertSame('Custom Value', $data['customField']);
        $this->assertSame('Seattle', $data['locationCity']);
        wp_reset_postdata();
    }
}
