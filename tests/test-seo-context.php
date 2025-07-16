<?php
class SeoContextHelperTest extends WP_UnitTestCase {
    public function tearDown(): void {
        delete_option('gm2_context_business_model');
        delete_option('gm2_context_industry_category');
        delete_option('gm2_context_target_audience');
        delete_option('gm2_context_unique_selling_points');
        remove_all_filters('gm2_seo_context');
        parent::tearDown();
    }

    public function test_helper_returns_sanitized_and_filtered_values() {
        update_option('gm2_context_business_model', '<b>Model</b>');
        update_option('gm2_context_industry_category', ' <i>Tech</i> ');
        update_option('gm2_context_target_audience', "Audience <script>bad()</script>");
        update_option('gm2_context_unique_selling_points', 'USP <span>great</span>');

        $filtered = null;
        add_filter('gm2_seo_context', function($context) use (&$filtered) {
            $filtered = $context;
            $context['industry_category'] = 'filtered';
            return $context;
        });

        $context = gm2_get_seo_context();

        $this->assertIsArray($filtered);
        $this->assertSame(sanitize_textarea_field('<b>Model</b>'), $filtered['business_model']);
        $this->assertSame(sanitize_text_field(' <i>Tech</i> '), $filtered['industry_category']);
        $this->assertSame(sanitize_textarea_field("Audience <script>bad()</script>"), $filtered['target_audience']);
        $this->assertSame(sanitize_textarea_field('USP <span>great</span>'), $filtered['unique_selling_points']);

        $this->assertSame('filtered', $context['industry_category']);
        $this->assertSame($filtered['business_model'], $context['business_model']);
        $this->assertSame($filtered['target_audience'], $context['target_audience']);
        $this->assertSame($filtered['unique_selling_points'], $context['unique_selling_points']);
    }
}
