<?php
class SeoContextHelperTest extends WP_UnitTestCase {
    public function tearDown(): void {
        delete_option('gm2_context_business_model');
        delete_option('gm2_context_industry_category');
        delete_option('gm2_context_target_audience');
        delete_option('gm2_context_unique_selling_points');
        delete_option('gm2_context_revenue_streams');
        delete_option('gm2_context_primary_goal');
        delete_option('gm2_context_brand_voice');
        delete_option('gm2_context_competitors');
        remove_all_filters('gm2_seo_context');
        parent::tearDown();
    }

    public function test_helper_returns_sanitized_and_filtered_values() {
        update_option('gm2_context_business_model', '<b>Model</b>');
        update_option('gm2_context_industry_category', ' <i>Tech</i> ');
        update_option('gm2_context_target_audience', "Audience <script>bad()</script>");
        update_option('gm2_context_unique_selling_points', 'USP <span>great</span>');
        update_option('gm2_context_revenue_streams', 'Ads <b>Subscriptions</b>');
        update_option('gm2_context_primary_goal', '<i>Increase sales</i>');
        update_option('gm2_context_brand_voice', 'Friendly <script>alert(1)</script>');
        update_option('gm2_context_competitors', 'Comp <span>A</span>, CompB');

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
        $this->assertSame(sanitize_textarea_field('Ads <b>Subscriptions</b>'), $filtered['revenue_streams']);
        $this->assertSame(sanitize_textarea_field('<i>Increase sales</i>'), $filtered['primary_goal']);
        $this->assertSame(sanitize_textarea_field('Friendly <script>alert(1)</script>'), $filtered['brand_voice']);
        $this->assertSame(sanitize_textarea_field('Comp <span>A</span>, CompB'), $filtered['competitors']);

        $this->assertSame('filtered', $context['industry_category']);
        $this->assertSame($filtered['business_model'], $context['business_model']);
        $this->assertSame($filtered['target_audience'], $context['target_audience']);
        $this->assertSame($filtered['unique_selling_points'], $context['unique_selling_points']);
        $this->assertSame($filtered['revenue_streams'], $context['revenue_streams']);
        $this->assertSame($filtered['primary_goal'], $context['primary_goal']);
        $this->assertSame($filtered['brand_voice'], $context['brand_voice']);
        $this->assertSame($filtered['competitors'], $context['competitors']);
    }
}
