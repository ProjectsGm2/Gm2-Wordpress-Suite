<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_JSON extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'json' );
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled         = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<textarea name="' . esc_attr( $this->key ) . '" rows="5" cols="40"' . $disabled . $placeholder_attr . '>' . esc_textarea( $value ) . '</textarea>';
    }

    public function sanitize_field_value( $value ) {
        if ( '' === trim( $value ) ) {
            return '';
        }
        $decoded = json_decode( $value, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return '';
        }
        return wp_json_encode( $decoded );
    }
}
