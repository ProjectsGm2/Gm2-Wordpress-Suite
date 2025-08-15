<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Media extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $button   = '<button class="button gm2-media-upload" data-target="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html__( 'Select Media', 'gm2-wordpress-suite' ) . '</button>';
        $src      = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';
        $preview  = '<div class="gm2-media-preview">' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="" />' : '' ) . '</div>';
        echo '<div class="gm2-media-field"><input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . ' />' . $button . $preview . '</div>';
    }

    public function sanitize( $value ) {
        return absint( $value );
    }
}
