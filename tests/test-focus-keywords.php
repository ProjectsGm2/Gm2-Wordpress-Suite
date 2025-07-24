<?php
class FocusKeywordsHelperTest extends WP_UnitTestCase {
    public function test_get_used_focus_keywords_returns_unique_lowercase() {
        $p1 = self::factory()->post->create();
        $p2 = self::factory()->post->create();
        update_post_meta($p1, '_gm2_focus_keywords', 'Alpha, Beta');
        update_post_meta($p2, '_gm2_focus_keywords', 'beta, Gamma');

        $term = self::factory()->category->create();
        update_term_meta($term, '_gm2_focus_keywords', 'Gamma, Delta');

        $keywords = gm2_get_used_focus_keywords();
        sort($keywords);
        $this->assertSame(['alpha','beta','delta','gamma'], $keywords);
    }
}

