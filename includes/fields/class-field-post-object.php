<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Post_Object extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $post_type = $this->args['post_type'] ?? 'post';
        $posts = get_posts( array( 'post_type' => $post_type, 'numberposts' => -1 ) );
        echo '<select name="' . esc_attr( $this->key ) . '"' . $disabled . '>';
        echo '<option value="">' . esc_html__( 'Select', 'gm2-wordpress-suite' ) . '</option>';
        foreach ( $posts as $post ) {
            $selected = selected( (int) $value, $post->ID, false );
            echo '<option value="' . esc_attr( $post->ID ) . '"' . $selected . '>' . esc_html( get_the_title( $post ) ) . '</option>';
        }
        echo '</select>';
    }

    public function sanitize( $value ) {
        $post_id = intval( $value );
        return get_post( $post_id ) ? $post_id : 0;
    }
}
