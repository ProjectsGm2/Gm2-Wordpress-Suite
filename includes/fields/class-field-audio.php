<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Audio extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'audio' );
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $button   = '<button class="button gm2-media-upload" data-target="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html__( 'Select Audio', 'gm2-wordpress-suite' ) . '</button>';
        $src      = $value ? wp_get_attachment_url( $value ) : '';
        $preview  = '<div class="gm2-media-preview">' . ( $src ? '<audio controls src="' . esc_url( $src ) . '"></audio>' : '' ) . '</div>';
        echo '<div class="gm2-media-field"><input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />' . $button . $preview . '</div>';
    }

    public function sanitize_field_value( $value ) {
        return absint( $value );
    }
}
