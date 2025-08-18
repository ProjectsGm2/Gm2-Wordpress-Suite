<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Url extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="url" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . ' />';
    }

    public function sanitize( $value ) {
        $value = esc_url_raw( $value );
        return $value && filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
    }
}
