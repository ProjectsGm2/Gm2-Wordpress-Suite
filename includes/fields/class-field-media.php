<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Media extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'media' );
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $button   = '<button class="button gm2-media-upload" data-target="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html__( 'Select Media', 'gm2-wordpress-suite' ) . '</button>';
        $src      = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';
        $preview  = '<div class="gm2-media-preview">' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="" />' : '' ) . '</div>';
        echo '<div class="gm2-media-field"><input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />' . $button . $preview . '</div>';
    }

    public function sanitize_field_value( $value ) {
        return absint( $value );
    }
}
