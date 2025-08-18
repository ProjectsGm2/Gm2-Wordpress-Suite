<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Sku extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="text" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" class="gm2-sku"' . $disabled . ' />';
    }

    public function sanitize( $value ) {
        $value   = sanitize_text_field( $value );
        $pattern = get_option( 'gm2_sku_pattern', '^[A-Z0-9-]+$' );
        $regex   = '/' . $pattern . '/';
        if ( @preg_match( $regex, $value ) !== 1 ) {
            return '';
        }
        return $value;
    }
}
