<?php

use Gm2\Content\Model\Definition;
use Gm2\Content\Registry\PostTypeRegistry;
use Gm2\Content\Registry\TaxonomyRegistry;

/**
 * Custom Post type and taxonomy registration helpers.
 *
 * @package gm2-wordpress-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register a custom post type and persist its configuration.
 *
 * Normalises supported arguments, merges them with any existing
 * configuration stored in the `gm2_custom_posts_config` option and
 * finally registers the post type via WordPress' `register_post_type()`.
 *
 * @deprecated 1.6.26 Use the content registry (`gm2/content/register`) to register post types.
 *
 * @param string $slug Post type slug.
 * @param array  $args Arguments to pass to `register_post_type()`.
 * @return void
 */
function gm2_cp_register_type( $slug, array $args ) {
    $slug = sanitize_key( $slug );
    if ( '' === $slug ) {
        return;
    }

    // Supported arguments for normalisation.
    $supported = [
        'labels',
        'supports',
        'menu_icon',
        'show_in_rest',
        'rewrite',
        'has_archive',
        'map_meta_cap',
        'capabilities',
    ];

    $clean = [];
    foreach ( $args as $key => $value ) {
        if ( ! in_array( $key, $supported, true ) ) {
            $clean[ $key ] = $value;
            continue;
        }

        switch ( $key ) {
            case 'labels':
                $clean['labels'] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
                break;
            case 'supports':
                $clean['supports'] = array_filter( array_map( 'sanitize_key', (array) $value ) );
                break;
            case 'menu_icon':
                $clean['menu_icon'] = sanitize_text_field( $value );
                break;
            case 'show_in_rest':
                $clean['show_in_rest'] = (bool) $value;
                break;
            case 'rewrite':
                $clean['rewrite'] = [];
                if ( is_array( $value ) ) {
                    if ( isset( $value['slug'] ) ) {
                        $clean['rewrite']['slug'] = sanitize_title( $value['slug'] );
                    }
                    if ( isset( $value['with_front'] ) ) {
                        $clean['rewrite']['with_front'] = (bool) $value['with_front'];
                    }
                    if ( isset( $value['feeds'] ) ) {
                        $clean['rewrite']['feeds'] = (bool) $value['feeds'];
                    }
                    if ( isset( $value['pages'] ) ) {
                        $clean['rewrite']['pages'] = (bool) $value['pages'];
                    }
                }
                if ( empty( $clean['rewrite'] ) ) {
                    unset( $clean['rewrite'] );
                }
                break;
            case 'has_archive':
                $clean['has_archive'] = is_string( $value ) ? sanitize_title( $value ) : (bool) $value;
                break;
            case 'map_meta_cap':
                $clean['map_meta_cap'] = (bool) $value;
                break;
            case 'capabilities':
                $clean['capabilities'] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
                break;
        }
    }

    $config = get_option( 'gm2_custom_posts_config', [] );
    if ( ! is_array( $config ) ) {
        $config = [];
    }

    $existing = $config['post_types'][ $slug ]['args'] ?? [];
    if ( ! is_array( $existing ) ) {
        $existing = [];
    }

    $merged = array_merge( $existing, $clean );

    if ( isset( $merged['taxonomies'] ) ) {
        $merged['taxonomies'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $merged['taxonomies'] ) ) ) );
    }

    $config['post_types'][ $slug ]['args'] = $merged;

    update_option( 'gm2_custom_posts_config', $config );

    $labels = isset( $merged['labels'] ) && is_array( $merged['labels'] ) ? $merged['labels'] : [];
    $default_label = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
    $singular = isset( $labels['singular_name'] ) && is_string( $labels['singular_name'] ) ? $labels['singular_name'] : '';
    $plural   = isset( $labels['name'] ) && is_string( $labels['name'] ) ? $labels['name'] : '';

    if ( '' === $singular ) {
        $singular = '' !== $plural ? $plural : $default_label;
    }

    if ( '' === $plural ) {
        $plural = '' !== $singular ? $singular : $default_label;
    }

    $supports    = isset( $merged['supports'] ) ? (array) $merged['supports'] : [];
    $has_archive = $merged['has_archive'] ?? null;
    $menu_icon   = isset( $merged['menu_icon'] ) ? (string) $merged['menu_icon'] : null;
    $rewrite     = isset( $merged['rewrite'] ) && is_array( $merged['rewrite'] ) ? $merged['rewrite'] : [];
    $cap_type    = 'post';
    if ( isset( $merged['capability_type'] ) ) {
        if ( is_array( $merged['capability_type'] ) ) {
            $cap_type = array_values( $merged['capability_type'] );
        } else {
            $cap_type = (string) $merged['capability_type'];
        }
    }
    $taxonomies = isset( $merged['taxonomies'] ) ? (array) $merged['taxonomies'] : [];

    $feeds_flag = $rewrite['feeds'] ?? null;
    $pages_flag = $rewrite['pages'] ?? null;

    $registry = new PostTypeRegistry();
    $definition = new Definition(
        $slug,
        $singular,
        $plural,
        $labels,
        $supports,
        $has_archive,
        $menu_icon,
        $rewrite,
        $cap_type,
        $taxonomies,
        $merged
    );

    $filter = null;
    if ( null !== $feeds_flag || null !== $pages_flag ) {
        $filter = static function ( $args ) use ( $feeds_flag, $pages_flag ) {
            if ( ! isset( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
                $args['rewrite'] = [];
            }
            if ( null !== $feeds_flag ) {
                $args['rewrite']['feeds'] = (bool) $feeds_flag;
            }
            if ( null !== $pages_flag ) {
                $args['rewrite']['pages'] = (bool) $pages_flag;
            }

            return $args;
        };

        add_filter( 'gm2/content/post_type_args', $filter, 10, 1 );
    }

    if ( null !== $filter ) {
        try {
            $registry->register( $definition );
        } finally {
            remove_filter( 'gm2/content/post_type_args', $filter, 10 );
        }
    } else {
        $registry->register( $definition );
    }
}

/**
 * Register a custom taxonomy and persist its configuration.
 *
 * @deprecated 1.6.26 Use the content registry (`gm2/content/register`) to register taxonomies.
 *
 * @param string       $slug        Taxonomy slug.
 * @param string|array $object_type Object type or array of object types the taxonomy applies to.
 * @param array        $args        Arguments to pass to `register_taxonomy()`.
 * @return void
 */
function gm2_cp_register_taxonomy( $slug, $object_type, array $args ) {
    $slug = sanitize_key( $slug );
    if ( '' === $slug ) {
        return;
    }

    $object_type = array_filter( array_map( 'sanitize_key', (array) $object_type ) );

    $supported = [
        'labels',
        'show_in_rest',
        'rewrite',
        'hierarchical',
        'capabilities',
    ];

    $clean = [];
    foreach ( $args as $key => $value ) {
        if ( ! in_array( $key, $supported, true ) ) {
            $clean[ $key ] = $value;
            continue;
        }

        switch ( $key ) {
            case 'labels':
                $clean['labels'] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
                break;
            case 'show_in_rest':
                $clean['show_in_rest'] = (bool) $value;
                break;
            case 'rewrite':
                $clean['rewrite'] = [];
                if ( is_array( $value ) ) {
                    if ( isset( $value['slug'] ) ) {
                        $clean['rewrite']['slug'] = sanitize_title( $value['slug'] );
                    }
                    if ( isset( $value['with_front'] ) ) {
                        $clean['rewrite']['with_front'] = (bool) $value['with_front'];
                    }
                    if ( isset( $value['hierarchical'] ) ) {
                        $clean['rewrite']['hierarchical'] = (bool) $value['hierarchical'];
                    }
                }
                if ( empty( $clean['rewrite'] ) ) {
                    unset( $clean['rewrite'] );
                }
                break;
            case 'hierarchical':
                $clean['hierarchical'] = (bool) $value;
                break;
            case 'capabilities':
                $clean['capabilities'] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
                break;
        }
    }

    $config = get_option( 'gm2_custom_posts_config', [] );
    if ( ! is_array( $config ) ) {
        $config = [];
    }

    $existing_args       = $config['taxonomies'][ $slug ]['args'] ?? [];
    $existing_object_map = $config['taxonomies'][ $slug ]['post_types'] ?? [];
    if ( ! is_array( $existing_args ) ) {
        $existing_args = [];
    }
    if ( ! is_array( $existing_object_map ) ) {
        $existing_object_map = [];
    }

    $merged_args   = array_merge( $existing_args, $clean );
    $merged_object = array_values( array_unique( array_merge( $existing_object_map, $object_type ) ) );

    $config['taxonomies'][ $slug ]['args']       = $merged_args;
    $config['taxonomies'][ $slug ]['post_types'] = $merged_object;

    update_option( 'gm2_custom_posts_config', $config );

    $labels = isset( $merged_args['labels'] ) && is_array( $merged_args['labels'] ) ? $merged_args['labels'] : [];
    $default_label = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
    $singular = isset( $labels['singular_name'] ) && is_string( $labels['singular_name'] ) ? $labels['singular_name'] : '';
    $plural   = isset( $labels['name'] ) && is_string( $labels['name'] ) ? $labels['name'] : '';

    if ( '' === $singular ) {
        $singular = '' !== $plural ? $plural : $default_label;
    }

    if ( '' === $plural ) {
        $plural = '' !== $singular ? $singular : $default_label;
    }

    $registry = new TaxonomyRegistry();
    $registry->register( $slug, $singular, $plural, $merged_object, $merged_args );
}
