<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Repeater extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $value    = is_array( $value ) ? $value : array();
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<div class="gm2-repeater" data-key="' . esc_attr( $this->key ) . '">';
        foreach ( $value as $row ) {
            echo '<div class="gm2-repeater-row"><input type="text" name="' . esc_attr( $this->key ) . '[]" value="' . esc_attr( $row ) . '"' . $disabled . ' /> <button type="button" class="button gm2-repeater-remove">&times;</button></div>';
        }
        echo '<div class="gm2-repeater-row"><input type="text" name="' . esc_attr( $this->key ) . '[]" value=""' . $disabled . ' /> <button type="button" class="button gm2-repeater-remove">&times;</button></div>';
        echo '<p><button type="button" class="button gm2-repeater-add" data-target="' . esc_attr( $this->key ) . '">' . esc_html__( 'Add Row', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';
    }

    public function sanitize( $value ) {
        if ( is_array( $value ) ) {
            return array_filter( array_map( 'sanitize_text_field', $value ) );
        }
        return array();
    }
}
