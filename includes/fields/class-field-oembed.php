<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Oembed extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        if ( $context_type === 'public' ) {
            echo gm2_render_oembed( (string) $value );
            return;
        }
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="url" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        return esc_url_raw( $value );
    }
}
