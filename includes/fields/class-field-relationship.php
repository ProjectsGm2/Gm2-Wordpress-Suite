<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Relationship extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $vals = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
        echo '<input type="text" name="' . esc_attr( $this->key ) . '[]" value="' . esc_attr( implode( ',', $vals ) ) . '" class="gm2-relationship" />';
    }

    public function sanitize( $value ) {
        $value = is_array( $value ) ? $value : array( $value );
        return array_filter( array_map( 'absint', $value ) );
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $old = get_post_meta( $object_id, $this->key, true );
        $old = is_array( $old ) ? $old : array();
        $new = $this->sanitize( $value );
        update_post_meta( $object_id, $this->key, $new );
        $remove = array_diff( $old, $new );
        foreach ( $remove as $rid ) {
            $related = get_post_meta( $rid, $this->key, true );
            $related = is_array( $related ) ? $related : array();
            $related = array_diff( $related, array( $object_id ) );
            update_post_meta( $rid, $this->key, $related );
        }
        foreach ( $new as $rid ) {
            $related = get_post_meta( $rid, $this->key, true );
            $related = is_array( $related ) ? $related : array();
            if ( ! in_array( $object_id, $related, true ) ) {
                $related[] = $object_id;
                update_post_meta( $rid, $this->key, $related );
            }
        }
    }
}
