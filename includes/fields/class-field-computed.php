<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Computed extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value    = $this->calculate_value( $object_id, $context_type );
        $disabled = $this->args['disabled'] ?? false ? ' data-disabled="1"' : '';
        echo '<span class="gm2-computed" data-key="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html( $value ) . '</span>';
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $computed = $this->calculate_value( $object_id, $context_type );
        if ( $computed !== null && $computed !== '' ) {
            $this->update_value( $object_id, $computed, $context_type );
        } else {
            $this->delete_value( $object_id, $context_type );
        }
    }

    protected function calculate_value( $object_id, $context_type ) {
        $formula = $this->args['formula'] ?? null;
        $cb      = $this->args['callback'] ?? null;

        if ( $formula ) {
            $value = $this->evaluate_formula( $formula, $object_id, $context_type );
            if ( $value !== null ) {
                return $value;
            }
        }

        if ( is_callable( $cb ) ) {
            return call_user_func( $cb, $object_id );
        }

        return null;
    }

    protected function evaluate_formula( $formula, $object_id, $context_type ) {
        preg_match_all( '/{([A-Za-z0-9_\-]+)}/', $formula, $matches );
        $replacements = array();
        foreach ( $matches[1] as $key ) {
            $val = $this->get_meta_value( $object_id, $key, $context_type );
            if ( is_numeric( $val ) ) {
                $replacements[ '{' . $key . '}' ] = $val;
            } else {
                $replacements[ '{' . $key . '}' ] = '\'' . str_replace( '\'', '\\' . '\'', (string) $val ) . '\'';
            }
        }
        $expr = strtr( $formula, $replacements );

        $without_strings = preg_replace( '/(["\']).*?\1/', '', $expr );
        if ( preg_match( '/[^0-9+\-*\/().\'" ]/', $without_strings ) ) {
            return null;
        }

        try {
            // phpcs:ignore -- expression is sanitized above.
            return eval( 'return ' . $expr . ';' );
        } catch ( \Throwable $e ) {
            return null;
        }
    }

    protected function get_meta_value( $object_id, $key, $context_type ) {
        switch ( $context_type ) {
            case 'user':
                return get_user_meta( $object_id, $key, true );
            case 'term':
                return get_term_meta( $object_id, $key, true );
            case 'comment':
                return get_comment_meta( $object_id, $key, true );
            case 'option':
                return get_option( $key );
            case 'site':
                return get_site_option( $key );
            default:
                return get_post_meta( $object_id, $key, true );
        }
    }
}

