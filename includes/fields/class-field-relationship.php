<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Relationship extends GM2_Field {

    /**
     * Determine the object type we are relating to. Defaults to posts.
     *
     * @var string
     */
    private $rel_type = 'post';

    /**
     * Sync strategy: none, one-way, two-way. Defaults to two-way to match
     * previous behaviour.
     *
     * @var string
     */
    private $sync = 'two-way';

    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'relationship' );
        $this->rel_type = in_array( $args['relationship_type'] ?? 'post', array( 'post', 'term', 'user', 'role' ), true ) ? $args['relationship_type'] : 'post';
        $this->sync     = in_array( $args['sync'] ?? 'two-way', array( 'none', 'one-way', 'two-way' ), true ) ? $args['sync'] : 'two-way';
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $vals     = is_array( $value ) ? $value : ( $value ? array( $value ) : array() );
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="text" name="' . esc_attr( $this->key ) . '[]" value="' . esc_attr( implode( ',', $vals ) ) . '" class="gm2-relationship"' . $disabled . $placeholder_attr . ' />';
    }

    public function sanitize_field_value( $value ) {
        $value = is_array( $value ) ? $value : array( $value );
        $out   = array();
        foreach ( $value as $v ) {
            if ( 'role' === $this->rel_type ) {
                $v = sanitize_key( $v );
                if ( $v ) {
                    $out[] = $v;
                }
            } else {
                $v = absint( $v );
                if ( $v ) {
                    $out[] = $v;
                }
            }
        }
        return $out;
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $old = $this->get_related( $object_id, $context_type );
        $new = $this->sanitize( $value );
        $this->update_related( $object_id, $new, $context_type );

        if ( 'none' === $this->sync ) {
            return;
        }

        $remove = array_diff( $old, $new );
        if ( 'two-way' === $this->sync ) {
            foreach ( $remove as $rid ) {
                $related = $this->get_related( $rid, $this->rel_type );
                $related = array_diff( $related, array( $object_id ) );
                $this->update_related( $rid, $related, $this->rel_type );
            }
        }

        foreach ( $new as $rid ) {
            $related = $this->get_related( $rid, $this->rel_type );
            if ( ! in_array( $object_id, $related, true ) ) {
                $related[] = $object_id;
                $this->update_related( $rid, $related, $this->rel_type );
            }
        }
    }

    private function get_related( $object_id, $type ) {
        switch ( $type ) {
            case 'user':
                $val = get_user_meta( $object_id, $this->key, true );
                break;
            case 'term':
                $val = get_term_meta( $object_id, $this->key, true );
                break;
            case 'role':
                $val = get_option( $this->key . '_role_' . $object_id, array() );
                break;
            default:
                $val = get_post_meta( $object_id, $this->key, true );
        }
        return is_array( $val ) ? $val : array();
    }

    private function update_related( $object_id, $vals, $type ) {
        switch ( $type ) {
            case 'user':
                update_user_meta( $object_id, $this->key, $vals );
                break;
            case 'term':
                update_term_meta( $object_id, $this->key, $vals );
                break;
            case 'role':
                update_option( $this->key . '_role_' . $object_id, $vals );
                break;
            default:
                update_post_meta( $object_id, $this->key, $vals );
        }
    }
}
