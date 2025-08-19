<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Video extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $button   = '<button class="button gm2-media-upload" data-target="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html__( 'Select Video', 'gm2-wordpress-suite' ) . '</button>';
        $src      = $value ? wp_get_attachment_url( $value ) : '';
        $preview  = '<div class="gm2-media-preview">' . ( $src ? '<video controls src="' . esc_url( $src ) . '" width="320"></video>' : '' ) . '</div>';
        echo '<div class="gm2-media-field"><input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />' . $button . $preview . '</div>';
    }

    public function sanitize( $value ) {
        return absint( $value );
    }
}
