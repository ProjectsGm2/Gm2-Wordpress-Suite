<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Radio extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $options  = $this->args['options'] ?? array();
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        foreach ( $options as $ov => $ol ) {
            echo '<label><input type="radio" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $ov ) . '"' . checked( $value, $ov, false ) . $disabled . $placeholder_attr . ' /> ' . esc_html( $ol ) . '</label><br />';
        }
    }

    public function sanitize( $value ) {
        return sanitize_text_field( $value );
    }
}
