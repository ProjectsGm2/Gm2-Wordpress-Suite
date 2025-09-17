<?php

namespace Gm2\SEO;

class Meta_Registration {
    public static function init(): void {
        add_action('init', [__CLASS__, 'register'], 20);
    }

    public static function get_post_meta_keys(): array {
        return array_keys(self::get_post_meta_definitions());
    }

    public static function get_term_meta_keys(): array {
        return array_keys(self::get_term_meta_definitions());
    }

    public static function register(): void {
        foreach (self::get_supported_post_types() as $post_type) {
            foreach (self::get_post_meta_definitions() as $meta_key => $definition) {
                register_post_meta(
                    $post_type,
                    $meta_key,
                    self::build_args($definition, $post_type)
                );
            }
        }

        foreach (self::get_supported_taxonomies() as $taxonomy) {
            foreach (self::get_term_meta_definitions() as $meta_key => $definition) {
                register_term_meta(
                    $taxonomy,
                    $meta_key,
                    self::build_args($definition, $taxonomy)
                );
            }
        }
    }

    private static function build_args(array $definition, string $subtype): array {
        return [
            'type'              => $definition['schema']['type'],
            'single'            => true,
            'show_in_rest'      => [
                'schema' => $definition['schema'],
            ],
            'sanitize_callback' => $definition['sanitize_callback'],
            'object_subtype'    => $subtype,
        ];
    }

    private static function get_post_meta_definitions(): array {
        return array_merge(
            self::get_shared_meta_definitions(),
            [
                '_gm2_link_rel' => [
                    'sanitize_callback' => [__CLASS__, 'sanitize_link_rel_map'],
                    'schema'            => [
                        'type'        => 'string',
                        'context'     => ['view', 'edit'],
                        'description' => 'JSON encoded map of link rel directives keyed by URL.',
                    ],
                ],
                '_aeseo_lcp_override' => [
                    'sanitize_callback' => [__CLASS__, 'sanitize_lcp_override'],
                    'schema'            => [
                        'type'    => 'string',
                        'context' => ['view', 'edit'],
                    ],
                ],
                '_aeseo_lcp_disable' => self::boolean_definition(),
            ]
        );
    }

    private static function get_term_meta_definitions(): array {
        return self::get_shared_meta_definitions();
    }

    private static function get_shared_meta_definitions(): array {
        return [
            '_gm2_title'               => self::string_definition('sanitize_text_field'),
            '_gm2_description'         => self::string_definition('sanitize_textarea_field'),
            '_gm2_noindex'             => self::boolean_definition(),
            '_gm2_nofollow'            => self::boolean_definition(),
            '_gm2_canonical'           => self::string_definition('esc_url_raw', [
                'format' => 'uri',
            ]),
            '_gm2_focus_keywords'      => self::string_definition('sanitize_text_field'),
            '_gm2_long_tail_keywords'  => self::string_definition('sanitize_text_field'),
            '_gm2_search_intent'       => self::string_definition('sanitize_text_field'),
            '_gm2_focus_keyword_limit' => self::integer_definition('absint', [
                'minimum' => 0,
            ]),
            '_gm2_number_of_words'     => self::integer_definition('absint', [
                'minimum' => 0,
            ]),
            '_gm2_improve_readability' => self::boolean_definition(),
            '_gm2_max_snippet'         => self::string_definition('sanitize_text_field'),
            '_gm2_max_image_preview'   => self::string_definition('sanitize_text_field'),
            '_gm2_max_video_preview'   => self::string_definition('sanitize_text_field'),
            '_gm2_og_image'            => self::integer_definition('absint', [
                'minimum' => 0,
            ]),
            '_gm2_schema_type'         => self::string_definition('sanitize_text_field'),
            '_gm2_schema_brand'        => self::string_definition('sanitize_text_field'),
            '_gm2_schema_rating'       => self::string_definition('sanitize_text_field'),
        ];
    }

    private static function string_definition(string|array $sanitize, array $schema = []): array {
        $schema = array_merge(
            [
                'type'    => 'string',
                'context' => ['view', 'edit'],
            ],
            $schema
        );

        return [
            'sanitize_callback' => $sanitize,
            'schema'            => $schema,
        ];
    }

    private static function integer_definition(string|array $sanitize, array $schema = []): array {
        $schema = array_merge(
            [
                'type'    => 'integer',
                'context' => ['view', 'edit'],
            ],
            $schema
        );

        return [
            'sanitize_callback' => $sanitize,
            'schema'            => $schema,
        ];
    }

    private static function boolean_definition(): array {
        return [
            'sanitize_callback' => [__CLASS__, 'sanitize_checkbox'],
            'schema'            => [
                'type'    => 'boolean',
                'context' => ['view', 'edit'],
            ],
        ];
    }

    private static function get_supported_post_types(): array {
        $args = [
            'public'              => true,
            'show_ui'             => true,
            'exclude_from_search' => false,
        ];
        $types = get_post_types($args, 'names');
        unset($types['attachment']);
        $types = apply_filters('gm2_supported_post_types', array_values($types));
        return array_values(array_unique($types));
    }

    private static function get_supported_taxonomies(): array {
        $taxonomies = ['category'];
        if (taxonomy_exists('product_cat')) {
            $taxonomies[] = 'product_cat';
        }
        if (taxonomy_exists('brand')) {
            $taxonomies[] = 'brand';
        }
        if (taxonomy_exists('product_brand')) {
            $taxonomies[] = 'product_brand';
        }
        return array_values(array_unique($taxonomies));
    }

    public static function sanitize_checkbox($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return absint($value) > 0 ? '1' : '0';
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ($value === '') {
                return '0';
            }
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return '1';
            }
            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return '0';
            }
        }

        return !empty($value) ? '1' : '0';
    }

    public static function sanitize_link_rel_map($value): string {
        if (is_array($value)) {
            $value = wp_json_encode($value);
        }

        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return '';
        }

        $sanitized = [];
        foreach ($decoded as $href => $rel) {
            if (!is_scalar($href)) {
                continue;
            }

            $href = esc_url_raw((string) $href);
            if ($href === '') {
                continue;
            }

            if (is_array($rel)) {
                $rel = implode(' ', array_map('sanitize_text_field', array_map('strval', $rel)));
            } elseif (is_scalar($rel)) {
                $rel = sanitize_text_field((string) $rel);
            } else {
                $rel = '';
            }

            $sanitized[$href] = $rel;
        }

        return wp_json_encode($sanitized);
    }

    public static function sanitize_lcp_override($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (is_numeric($value)) {
            return (string) absint($value);
        }

        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $sanitized = esc_url_raw($value);
        return $sanitized === '' ? '' : $sanitized;
    }
}
