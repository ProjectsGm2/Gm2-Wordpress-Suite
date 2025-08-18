<?php
// Rendering helpers for custom field types.

if ( ! function_exists( 'gm2_render_markdown' ) ) {
    function gm2_render_markdown( $markdown ) {
        if ( $markdown === null || $markdown === '' ) {
            return '';
        }
        $html = (string) $markdown;
        $html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
        $html = preg_replace( '/\*(.+?)\*/s', '<em>$1</em>', $html );
        $html = preg_replace( '/`(.+?)`/s', '<code>$1</code>', $html );
        $html = preg_replace( '/\[(.+?)\]\((https?:\/\/[^\s]+)\)/', '<a href="$2">$1</a>', $html );
        $html = wpautop( $html );
        return wp_kses_post( $html );
    }
}

if ( ! function_exists( 'gm2_render_code' ) ) {
    function gm2_render_code( $code, $language = '' ) {
        $lang = $language ? ' class="language-' . esc_attr( $language ) . '"' : '';
        return '<pre><code' . $lang . '>' . esc_html( (string) $code ) . '</code></pre>';
    }
}

if ( ! function_exists( 'gm2_render_oembed' ) ) {
    function gm2_render_oembed( $url ) {
        if ( ! $url ) {
            return '';
        }
        $embed = wp_oembed_get( $url );
        if ( $embed ) {
            return $embed;
        }
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
    }
}
