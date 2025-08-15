<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Geospatial extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        echo '<input type="text" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" placeholder="lat,lng" />';
    }
}
