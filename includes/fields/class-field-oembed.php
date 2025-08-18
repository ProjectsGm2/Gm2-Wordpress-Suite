<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Oembed extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        if ( $context_type === 'public' ) {
            echo gm2_render_oembed( (string) $value );
            return;
        }
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="url" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . ' />';
    }

    public function sanitize( $value ) {
        return esc_url_raw( $value );
    }
}
