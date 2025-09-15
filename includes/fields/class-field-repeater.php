<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Repeater extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'repeater' );
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $rows     = is_array( $value ) ? $value : array();
        $disabled = $this->args['disabled'] ?? false ? ' data-disabled="1"' : '';
        echo '<div class="gm2-repeater" data-key="' . esc_attr( $this->key ) . '"' . $disabled . '>';
        $fields = $this->args['sub_fields'] ?? array();
        foreach ( $rows as $i => $row ) {
            echo '<div class="gm2-repeater-row">';
            gm2_render_field_group( $fields, $object_id, $context_type, $row, $this->key . '[' . $i . ']' );
            echo '<button type="button" class="button gm2-repeater-remove">&times;</button></div>';
        }
        echo '<div class="gm2-repeater-row gm2-repeater-template" style="display:none;">';
        gm2_render_field_group( $fields, $object_id, $context_type, array(), $this->key . '[__i__]' );
        echo '<button type="button" class="button gm2-repeater-remove">&times;</button></div>';
        echo '<p><button type="button" class="button gm2-repeater-add" data-target="' . esc_attr( $this->key ) . '">' . esc_html__( 'Add Row', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '</div>';
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $rows   = is_array( $value ) ? $value : array();
        $fields = $this->args['sub_fields'] ?? array();
        $clean  = array();
        foreach ( $rows as $row ) {
            $original_request = $_REQUEST;
            if ( is_array( $row ) ) {
                $_REQUEST = array_merge( $_REQUEST, $row );
            }
            $row_clean = array();
            foreach ( $fields as $sub_key => $sub_field ) {
                if ( ! \Gm2\Gm2_Capability_Manager::can_edit_field( $sub_key, $object_id ) ) {
                    continue;
                }
                $state = gm2_evaluate_conditions( $sub_field, $object_id );
                if ( ! $state['show'] ) {
                    continue;
                }
                $val   = $row[ $sub_key ] ?? null;
                $valid = gm2_validate_field( $sub_key, $sub_field, $val, $object_id, $context_type );
                if ( is_wp_error( $valid ) ) {
                    $_REQUEST = $original_request;
                    wp_die( $valid->get_error_message() );
                }
                $type  = $sub_field['type'] ?? 'text';
                $class = gm2_get_field_type_class( $type );
                if ( $class && class_exists( $class ) ) {
                    $obj                = new $class( $sub_key, $sub_field );
                    $row_clean[ $sub_key ] = $obj->sanitize( $val );
                }
            }
            $_REQUEST = $original_request;
            if ( ! empty( $row_clean ) ) {
                $clean[] = $row_clean;
            }
        }

        if ( empty( $clean ) ) {
            $this->delete_value( $object_id, $context_type );
        } else {
            $this->update_value( $object_id, $clean, $context_type );
        }
    }
}
