<?php

namespace Gm2 {
    if (!defined('ABSPATH')) {
        exit;
    }
}

namespace {
    function gm2_get_seo_context() {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $context = [
            'business_model'        => sanitize_textarea_field(get_option('gm2_context_business_model', '')),
            'industry_category'     => sanitize_text_field(get_option('gm2_context_industry_category', '')),
            'target_audience'       => sanitize_textarea_field(get_option('gm2_context_target_audience', '')),
            'unique_selling_points' => sanitize_textarea_field(get_option('gm2_context_unique_selling_points', '')),
            'revenue_streams'       => sanitize_textarea_field(get_option('gm2_context_revenue_streams', '')),
            'primary_goal'          => sanitize_textarea_field(get_option('gm2_context_primary_goal', '')),
            'brand_voice'           => sanitize_textarea_field(get_option('gm2_context_brand_voice', '')),
            'competitors'           => sanitize_textarea_field(get_option('gm2_context_competitors', '')),
            'core_offerings'        => sanitize_textarea_field(get_option('gm2_context_core_offerings', '')),
            'geographic_focus'      => sanitize_textarea_field(get_option('gm2_context_geographic_focus', '')),
            'keyword_data'          => sanitize_textarea_field(get_option('gm2_context_keyword_data', '')),
            'competitor_landscape'  => sanitize_textarea_field(get_option('gm2_context_competitor_landscape', '')),
            'success_metrics'       => sanitize_textarea_field(get_option('gm2_context_success_metrics', '')),
            'buyer_personas'        => sanitize_textarea_field(get_option('gm2_context_buyer_personas', '')),
            'project_description'   => sanitize_textarea_field(get_option('gm2_context_project_description', '')),
            'custom_prompts'        => sanitize_textarea_field(get_option('gm2_context_custom_prompts', '')),
        ];

        if ($context['project_description'] === '') {
            $context['project_description'] = gm2_get_project_description();
        }
        /**
         * Filter the assembled SEO context options.
         *
         * The array contains sanitized values from all context settings such as
         * `gm2_context_business_model`, `gm2_context_industry_category`,
         * `gm2_context_target_audience`, `gm2_context_unique_selling_points`,
         * `gm2_context_revenue_streams`, `gm2_context_primary_goal`,
         * `gm2_context_brand_voice`, `gm2_context_competitors`,
         * `gm2_context_core_offerings`, `gm2_context_geographic_focus`,
         * `gm2_context_keyword_data`, `gm2_context_competitor_landscape`,
         * `gm2_context_success_metrics`, `gm2_context_buyer_personas`,
         * `gm2_context_project_description` and `gm2_context_custom_prompts`.
         *
         * @param array $context Associative array of context strings.
         */
        $context = apply_filters('gm2_seo_context', $context);
        $cached  = $context;
        return $context;
    }

    function gm2_get_project_description() {
        $desc = sanitize_textarea_field(get_option('gm2_project_description', ''));
        if ($desc === '') {
            $desc = sanitize_textarea_field(get_bloginfo('description'));
        }
        if ($desc === '' && isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
            $clean = wp_strip_all_tags($GLOBALS['post']->post_content);
            $desc  = gm2_substr($clean, 0, 160);
        }
        return $desc;
    }

    /**
     * Multibyte-safe substring helper.
     */
    function gm2_substr($string, $start, $length = null) {
        if (function_exists('mb_substr')) {
            return mb_substr($string, $start, $length, 'UTF-8');
        }
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }

    function gm2_get_business_context_prompt() {
        $prompt = trim(get_option('gm2_context_ai_prompt', ''));
        return $prompt === '' ? '' : $prompt;
    }

    function gm2_ai_send_prompt($prompt, $args = []) {
        $provider = get_option('gm2_ai_provider', 'chatgpt');
        $map = [
            'chatgpt'     => '\\Gm2\\AI\\ChatGPTProvider',
            'gemma'       => '\\Gm2\\AI\\GemmaProvider',
            'gemma_local' => '\\Gm2\\AI\\LocalGemmaProvider',
            'llama'       => '\\Gm2\\AI\\LlamaProvider',
            'llama_local' => '\\Gm2\\AI\\LocalLlamaProvider',
        ];
        $class = $map[$provider] ?? $map['chatgpt'];

        if (!class_exists($class)) {
            return new \WP_Error('invalid_provider', 'Invalid AI provider');
        }

        $instance = new $class();
        if (!($instance instanceof \Gm2\AI\ProviderInterface)) {
            return new \WP_Error('invalid_provider', 'Invalid AI provider');
        }

        return $instance->query($prompt, $args);
    }

    /**
     * Retrieve the batch size used for SEO-related post queries.
     *
     * Developers can adjust the default batch size (500) by filtering
     * `gm2_seo_post_batch_size`.
     *
     * @return int
     */
    function gm2_get_seo_post_batch_size(): int {
        /**
         * Filters the batch size used for SEO-related post queries.
         *
         * @param int $size Number of posts processed per query. Default 500.
         */
        $size = (int) apply_filters('gm2_seo_post_batch_size', 500);
        if ($size < 1) {
            $size = 500;
        }
        return $size;
    }

    /**
     * Retrieve a deduplicated list of all focus keywords used across posts and terms.
     *
     * @return string[] Lowercase focus keywords.
     */
    function gm2_get_used_focus_keywords() {
        $values     = [];
        $post_types = get_post_types(['public' => true], 'names');
        $batch_size = gm2_get_seo_post_batch_size();
        $paged      = 1;

        do {
            $query = new \WP_Query([
                'post_type'      => $post_types,
                'post_status'    => 'any',
                'meta_key'       => '_gm2_focus_keywords',
                'fields'         => 'ids',
                'posts_per_page' => $batch_size,
                'paged'          => $paged,
                'no_found_rows'  => true,
            ]);
            $post_ids = $query->posts;
            if (empty($post_ids)) {
                break;
            }

            foreach ($post_ids as $pid) {
                $val = get_post_meta($pid, '_gm2_focus_keywords', true);
                if ($val !== '') {
                    $values[] = $val;
                }
            }

            $paged++;
        } while (count($post_ids) === $batch_size);

        $term_ids = get_terms([
            'taxonomy'   => get_taxonomies([], 'names'),
            'hide_empty' => false,
            'meta_query' => [ [ 'key' => '_gm2_focus_keywords', 'compare' => 'EXISTS' ] ],
            'fields'     => 'ids',
        ]);
        if (!is_wp_error($term_ids)) {
            foreach ($term_ids as $tid) {
                $val = get_term_meta($tid, '_gm2_focus_keywords', true);
                if ($val !== '') {
                    $values[] = $val;
                }
            }
        }

        $list = [];
        foreach ($values as $str) {
            foreach (explode(',', $str) as $kw) {
                $kw = strtolower(trim($kw));
                if ($kw !== '') {
                    $list[] = $kw;
                }
            }
        }
        return array_values(array_unique($list));
    }

    function gm2_infer_brand_name(int $post_id): string {
        $taxonomies = apply_filters('gm2_brand_taxonomies', ['brand', 'product_brand', 'pa_brand']);
        $terms      = wp_get_post_terms($post_id, $taxonomies, ['fields' => 'names']);
        if (!is_wp_error($terms) && !empty($terms)) {
            return sanitize_text_field($terms[0]);
        }

        $post_taxes = get_post_taxonomies($post_id);
        foreach ($post_taxes as $tax) {
            if (stripos($tax, 'brand') === false || in_array($tax, $taxonomies, true)) {
                continue;
            }
            $terms = wp_get_post_terms($post_id, $tax, ['fields' => 'names']);
            if (!is_wp_error($terms) && !empty($terms)) {
                return sanitize_text_field($terms[0]);
            }
        }
        return '';
    }

    function gm2_ai_clear() {
        $post_types = get_post_types(['public' => true], 'names');
        $batch_size = gm2_get_seo_post_batch_size();

        while (true) {
            $query = new \WP_Query([
                'post_type'      => $post_types,
                'post_status'    => 'any',
                'meta_key'       => '_gm2_ai_research',
                'fields'         => 'ids',
                'posts_per_page' => $batch_size,
                'paged'          => 1,
                'no_found_rows'  => true,
            ]);
            $post_ids = $query->posts;
            if (empty($post_ids)) {
                break;
            }

            foreach ($post_ids as $pid) {
                delete_post_meta($pid, '_gm2_ai_research');
            }
        }

        $terms = get_terms([
            'taxonomy'   => get_taxonomies([], 'names'),
            'hide_empty' => false,
            'meta_query' => [ [ 'key' => '_gm2_ai_research' ] ],
            'fields'     => 'ids',
        ]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $tid) {
                delete_term_meta($tid, '_gm2_ai_research');
            }
        }

        if (defined('GM2_CHATGPT_LOG_FILE') && file_exists(GM2_CHATGPT_LOG_FILE)) {
            file_put_contents(GM2_CHATGPT_LOG_FILE, '');
        }

        return true;
    }
}
