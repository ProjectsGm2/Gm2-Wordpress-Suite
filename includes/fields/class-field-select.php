<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Select extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $options = $this->args['options'] ?? array();
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<select name="' . esc_attr( $this->key ) . '"' . $disabled . '>';
        foreach ( $options as $ov => $ol ) {
            echo '<option value="' . esc_attr( $ov ) . '"' . selected( $value, $ov, false ) . '>' . esc_html( $ol ) . '</option>';
        }
        echo '</select>';
    }
}
