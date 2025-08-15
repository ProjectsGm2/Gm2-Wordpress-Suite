<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Wysiwyg extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $settings = array(
            'textarea_name' => $this->key,
            'textarea_rows' => $this->args['wysiwyg_rows'] ?? 10,
            'media_buttons' => $this->args['wysiwyg_media'] ?? true,
        );
        if ( $context_type === 'public' ) {
            echo wpautop( wp_kses_post( $value ) );
        } else {
            wp_editor( $value, $this->key, $settings );
        }
    }

    public function sanitize( $value ) {
        return wp_kses_post( $value );
    }
}
