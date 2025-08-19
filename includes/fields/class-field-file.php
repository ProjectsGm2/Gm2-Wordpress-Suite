<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_File extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $button   = '<button class="button gm2-media-upload" data-target="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html__( 'Select File', 'gm2-wordpress-suite' ) . '</button>';
        $url      = $value ? wp_get_attachment_url( $value ) : '';
        $name     = $url ? wp_basename( $url ) : '';
        $preview  = '<div class="gm2-media-preview">' . ( $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $name ) . '</a>' : '' ) . '</div>';
        echo '<div class="gm2-media-field"><input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />' . $button . $preview . '</div>';
    }

    public function sanitize( $value ) {
        return absint( $value );
    }
}
