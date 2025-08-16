<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Toggle extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $checked  = checked( $value, '1', false );
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        echo '<input type="checkbox" class="gm2-toggle" name="' . esc_attr( $this->key ) . '" value="1"' . $checked . $disabled . ' />';
    }

    public function sanitize( $value ) {
        return rest_sanitize_boolean( $value ) ? '1' : '';
    }

    public function validate( $value ) {
        return in_array( $value, array( '', '0', '1' ), true ) ? true : new WP_Error( 'gm2_toggle_invalid', __( 'Invalid toggle value.', 'gm2-wordpress-suite' ) );
    }

    public function get_rest_schema() {
        return array(
            'type'    => 'boolean',
            'context' => array( 'view', 'edit' ),
        );
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $value = $this->sanitize( $value );
        $valid = $this->validate( $value );
        if ( is_wp_error( $valid ) ) {
            return;
        }
        if ( $value === '' ) {
            $this->delete_value( $object_id, $context_type );
        } else {
            $this->update_value( $object_id, $value, $context_type );
        }
    }
}
