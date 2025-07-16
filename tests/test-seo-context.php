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
        delete_option('gm2_context_project_description');
        delete_option('gm2_context_custom_prompts');
        delete_option('gm2_project_description');
        remove_all_filters('gm2_seo_context');
        parent::tearDown();
    }

    public function test_helper_returns_sanitized_and_filtered_values() {
        $raw_options = [
            'gm2_context_business_model'        => '<b>Model</b>',
            'gm2_context_industry_category'     => ' <i>Tech</i> ',
            'gm2_context_target_audience'       => "Audience <script>bad()</script>",
            'gm2_context_unique_selling_points' => 'USP <span>great</span>',
            'gm2_context_revenue_streams'       => 'Ads <b>Subscriptions</b>',
            'gm2_context_primary_goal'          => '<i>Increase sales</i>',
            'gm2_context_brand_voice'           => 'Friendly <script>alert(1)</script>',
            'gm2_context_competitors'           => 'Comp <span>A</span>, CompB',
        ];

        foreach ($raw_options as $opt => $val) {
            update_option($opt, $val);
        }

        $filtered = null;
        add_filter('gm2_seo_context', function($context) use (&$filtered) {
            $filtered = $context;
            $context['industry_category'] = 'filtered';
            return $context;
        });

        $context = gm2_get_seo_context();

        $expected = [
            'business_model'        => sanitize_textarea_field($raw_options['gm2_context_business_model']),
            'industry_category'     => sanitize_text_field($raw_options['gm2_context_industry_category']),
            'target_audience'       => sanitize_textarea_field($raw_options['gm2_context_target_audience']),
            'unique_selling_points' => sanitize_textarea_field($raw_options['gm2_context_unique_selling_points']),
            'revenue_streams'       => sanitize_textarea_field($raw_options['gm2_context_revenue_streams']),
            'primary_goal'          => sanitize_textarea_field($raw_options['gm2_context_primary_goal']),
            'brand_voice'           => sanitize_textarea_field($raw_options['gm2_context_brand_voice']),
            'competitors'           => sanitize_textarea_field($raw_options['gm2_context_competitors']),
        ];

        $this->assertIsArray($filtered);
        foreach ($expected as $key => $val) {
            $this->assertSame($val, $filtered[$key]);
        }

        $this->assertSame('filtered', $context['industry_category']);
        foreach ($expected as $key => $val) {
            if ($key === 'industry_category') {
                continue;
            }
            $this->assertSame($val, $context[$key]);
        }
    }
}
