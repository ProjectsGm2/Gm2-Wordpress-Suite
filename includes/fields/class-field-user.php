<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_User extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $users = get_users();
        echo '<select name="' . esc_attr( $this->key ) . '"' . $disabled . '>';
        echo '<option value="">' . esc_html__( 'Select', 'gm2-wordpress-suite' ) . '</option>';
        foreach ( $users as $user ) {
            $selected = selected( (int) $value, $user->ID, false );
            echo '<option value="' . esc_attr( $user->ID ) . '"' . $selected . '>' . esc_html( $user->display_name ) . '</option>';
        }
        echo '</select>';
    }

    public function sanitize( $value ) {
        $user_id = intval( $value );
        return get_user_by( 'id', $user_id ) ? $user_id : 0;
    }
}
