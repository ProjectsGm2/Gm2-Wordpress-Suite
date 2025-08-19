<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Gallery extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $ids      = array_filter( array_map( 'absint', is_array( $value ) ? $value : explode( ',', (string) $value ) ) );
        $button   = '<button class="button gm2-gallery-upload" data-target="' . esc_attr( $this->key ) . '"' . $disabled . '>' . esc_html__( 'Select Images', 'gm2-wordpress-suite' ) . '</button>';
        $preview  = '<div class="gm2-media-preview">';
        foreach ( $ids as $id ) {
            $src = wp_get_attachment_image_url( $id, 'thumbnail' );
            if ( $src ) {
                $preview .= '<img src="' . esc_url( $src ) . '" alt="" />';
            }
        }
        $preview .= '</div>';
        echo '<div class="gm2-media-field"><input type="hidden" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '"' . $disabled . $placeholder_attr . ' />' . $button . $preview . '</div>';
    }

    public function sanitize( $value ) {
        $ids = is_array( $value ) ? $value : explode( ',', (string) $value );
        $ids = array_filter( array_map( 'absint', $ids ) );
        return $ids;
    }
}
