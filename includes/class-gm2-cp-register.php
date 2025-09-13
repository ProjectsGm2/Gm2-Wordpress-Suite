<?php
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
    $config['post_types'][ $slug ]['args'] = $merged;

    update_option( 'gm2_custom_posts_config', $config );

    register_post_type( $slug, $merged );
}

/**
 * Register a custom taxonomy and persist its configuration.
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
    $merged_object = array_unique( array_merge( $existing_object_map, $object_type ) );

    $config['taxonomies'][ $slug ]['args']       = $merged_args;
    $config['taxonomies'][ $slug ]['post_types'] = $merged_object;

    update_option( 'gm2_custom_posts_config', $config );

    register_taxonomy( $slug, $merged_object, $merged_args );
}
