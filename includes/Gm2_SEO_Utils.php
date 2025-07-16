<?php

namespace Gm2 {
    if (!defined('ABSPATH')) {
        exit;
    }
}

namespace {
    function gm2_get_seo_context() {
        $context = [
            'business_model'        => sanitize_textarea_field(get_option('gm2_context_business_model', '')),
            'industry_category'     => sanitize_text_field(get_option('gm2_context_industry_category', '')),
            'target_audience'       => sanitize_textarea_field(get_option('gm2_context_target_audience', '')),
            'unique_selling_points' => sanitize_textarea_field(get_option('gm2_context_unique_selling_points', '')),
            'revenue_streams'       => sanitize_textarea_field(get_option('gm2_context_revenue_streams', '')),
            'primary_goal'          => sanitize_textarea_field(get_option('gm2_context_primary_goal', '')),
            'brand_voice'           => sanitize_textarea_field(get_option('gm2_context_brand_voice', '')),
            'competitors'           => sanitize_textarea_field(get_option('gm2_context_competitors', '')),
        ];
        /**
         * Filter the assembled SEO context options.
         *
         * @param array $context Associative array of context strings.
         */
        $context = apply_filters('gm2_seo_context', $context);
        return $context;
    }
}
