<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Taxonomy_Terms extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value    = is_array( $value ) ? $value : array();
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $taxonomy = $this->args['taxonomy'] ?? 'category';
        $terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
        echo '<select name="' . esc_attr( $this->key ) . '[]" multiple' . $disabled . '>';
        foreach ( $terms as $term ) {
            $selected = in_array( $term->term_id, $value, true ) ? ' selected' : '';
            echo '<option value="' . esc_attr( $term->term_id ) . '"' . $selected . '>' . esc_html( $term->name ) . '</option>';
        }
        echo '</select>';
    }

    public function sanitize_field_value( $value ) {
        $value = is_array( $value ) ? $value : array();
        $taxonomy = $this->args['taxonomy'] ?? 'category';
        $clean  = array();
        foreach ( $value as $term_id ) {
            $term_id = intval( $term_id );
            if ( $term_id && get_term( $term_id, $taxonomy ) ) {
                $clean[] = $term_id;
            }
        }
        return $clean;
    }
}
