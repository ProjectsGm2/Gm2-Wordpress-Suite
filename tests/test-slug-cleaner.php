<?php
use Gm2\Gm2_SEO_Admin;

class SlugCleanerTest extends WP_UnitTestCase {
    public function test_stopwords_are_removed_from_slug() {
        update_option('gm2_clean_slugs', '1');
        update_option('gm2_slug_stopwords', "the of");
        $admin = new Gm2_SEO_Admin();
        $admin->run();
        $slug = sanitize_title('The Best of the Products');
        remove_filter('sanitize_title', [$admin, 'clean_slug'], 20);
        $this->assertSame('best-products', $slug);
    }
}
