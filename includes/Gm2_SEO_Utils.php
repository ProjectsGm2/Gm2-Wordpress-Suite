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
        $defaults = [
            'language-model' => 'gpt-3.5-turbo',
            'temperature'    => 1.0,
            'number-of-words'=> 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $api_key = get_option('gm2_chatgpt_api_key', '');
        if ($api_key === '') {
            return new \WP_Error('no_api_key', 'ChatGPT API key not set');
        }
        $model = in_array($args['language-model'], ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo'], true) ? $args['language-model'] : 'gpt-3.5-turbo';
        $temperature = floatval($args['temperature']);

        $payload = [
            'model'       => $model,
            'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
            'temperature' => $temperature,
        ];
        if ($args['number-of-words']) {
            $payload['max_tokens'] = intval($args['number-of-words']);
        }

        $endpoint = get_option('gm2_chatgpt_endpoint', 'https://api.openai.com/v1/chat/completions');
        $http_args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ];

        $response = wp_remote_post($endpoint, $http_args);

        $result = null;
        if (is_wp_error($response)) {
            $result = $response;
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $body   = wp_remote_retrieve_body($response);
            if ($status !== 200) {
                $data    = json_decode($body, true);
                $message = $data['error']['message'] ?? 'Non-200 response';
                $result  = new \WP_Error('api_error', $message);
            } else {
                if ($body === '') {
                    $result = '';
                } else {
                    $data = json_decode($body, true);
                    $result = $data['choices'][0]['message']['content'] ?? '';
                }
            }
        }

        if (get_option('gm2_enable_chatgpt_logging', '0') === '1') {
            $log_resp = is_wp_error($result) ? $result->get_error_message() : $result;
            $entry = wp_json_encode([
                'prompt'   => $prompt,
                'response' => $log_resp,
            ]);
            error_log($entry . PHP_EOL, 3, GM2_CHATGPT_LOG_FILE);
        }

        return $result;
    }

    /**
     * Retrieve a deduplicated list of all focus keywords used across posts and terms.
     *
     * @return string[] Lowercase focus keywords.
     */
    function gm2_get_used_focus_keywords() {
        $values = [];

        $post_ids = get_posts([
            'post_type'      => get_post_types(['public' => true], 'names'),
            'post_status'    => 'any',
            'meta_key'       => '_gm2_focus_keywords',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        foreach ($post_ids as $pid) {
            $val = get_post_meta($pid, '_gm2_focus_keywords', true);
            if ($val !== '') {
                $values[] = $val;
            }
        }

        $term_ids = get_terms([
            'taxonomy'   => get_taxonomies([], 'names'),
            'hide_empty' => false,
            'meta_query' => [ [ 'key' => '_gm2_focus_keywords' ] ],
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

    function gm2_ai_clear() {
        $post_ids = get_posts([
            'post_type'      => get_post_types(['public' => true], 'names'),
            'post_status'    => 'any',
            'meta_key'       => '_gm2_ai_research',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        foreach ($post_ids as $pid) {
            delete_post_meta($pid, '_gm2_ai_research');
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
