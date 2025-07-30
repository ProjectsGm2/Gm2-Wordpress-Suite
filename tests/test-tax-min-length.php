<?php
use Gm2\Gm2_SEO_Admin;

class TaxonomyMinLengthTest extends WP_UnitTestCase {
    public function test_notice_when_description_short() {
        update_option('gm2_tax_min_length', 5);
        $term_id = self::factory()->term->create(['taxonomy' => 'category']);
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $admin = new Gm2_SEO_Admin();
        $_POST['gm2_seo_nonce'] = wp_create_nonce('gm2_save_seo_meta');
        $_POST['description'] = 'one two';
        $admin->save_taxonomy_meta($term_id);
        $prop = new ReflectionProperty(Gm2_SEO_Admin::class, 'notices');
        $prop->setAccessible(true);
        $notices = $prop->getValue();
        $found = false;
        foreach ($notices as $n) {
            if (strpos($n['message'], '5') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}

