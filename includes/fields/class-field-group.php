<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Group extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = $this->args['disabled'] ?? false ? ' data-disabled="1"' : '';
        echo '<div class="gm2-field-group" data-key="' . esc_attr( $this->key ) . '"' . $disabled . '>';
        $fields = $this->args['fields'] ?? array();
        gm2_render_field_group( $fields, $object_id, $context_type, null, $this->key );
        echo '</div>';
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $fields = $this->args['fields'] ?? array();
        $vals   = is_array( $value ) ? $value : array();
        gm2_save_field_group( $fields, $object_id, $context_type, $vals );
    }
}
