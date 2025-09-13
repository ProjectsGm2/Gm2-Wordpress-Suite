<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class GM2_Field {
    protected $key;
    protected $args;

    public function __construct( $key, $args = array() ) {
        $this->key  = $key;
        $this->args = is_array( $args ) ? $args : array();
    }

    public function render_admin( $value, $object_id, $context_type ) {
        $label = $this->args['label'] ?? $this->key;
        echo '<p><label>' . esc_html( $label ) . '<br />';
        $placeholder = $this->args['placeholder'] ?? '';
        $this->render_field( $value, $object_id, $context_type, $placeholder );
        echo '</label></p>';
    }

    public function render_public( $value ) {
        echo '<div class="gm2-field gm2-field-' . esc_attr( $this->key ) . '">';
        $placeholder = $this->args['placeholder'] ?? '';
        $this->render_field( $value, 0, 'public', $placeholder );
        echo '</div>';
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled         = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        echo '<input type="text" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '"' . $disabled . $placeholder_attr . ' />';
    }

    /**
     * Sanitize a value for this field.
     *
     * @param mixed $value Raw value.
     * @return mixed Sanitized value after filters.
     */
    public function sanitize( $value ) {
        $value = $this->sanitize_field_value( $value );

        /**
         * Filters the sanitized value for a field.
         *
         * The dynamic portion of the hook name, {$this->key}, refers to the field key.
         *
         * @param mixed     $value Sanitized value.
         * @param GM2_Field $this  Field instance.
         */
        return apply_filters( "gm2_cp_field_sanitize_{$this->key}", $value, $this );
    }

    /**
     * Default field-specific sanitization callback.
     *
     * Child classes may override this to implement custom sanitization logic.
     *
     * @param mixed $value Raw value.
     * @return mixed Sanitized value.
     */
    protected function sanitize_field_value( $value ) {
        return sanitize_text_field( $value );
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        $value = $this->sanitize( $value );
        if ( $value === null || $value === '' ) {
            $this->delete_value( $object_id, $context_type );
        } else {
            $this->update_value( $object_id, $value, $context_type );
        }
    }

    protected function update_value( $object_id, $value, $context_type ) {
        switch ( $context_type ) {
            case 'user':
                update_user_meta( $object_id, $this->key, $value );
                break;
            case 'term':
                update_term_meta( $object_id, $this->key, $value );
                break;
            case 'comment':
                update_comment_meta( $object_id, $this->key, $value );
                break;
            case 'option':
                update_option( $this->key, $value );
                break;
            case 'site':
                update_site_option( $this->key, $value );
                break;
            default:
                update_post_meta( $object_id, $this->key, $value );
        }
    }

    protected function delete_value( $object_id, $context_type ) {
        switch ( $context_type ) {
            case 'user':
                delete_user_meta( $object_id, $this->key );
                break;
            case 'term':
                delete_term_meta( $object_id, $this->key );
                break;
            case 'comment':
                delete_comment_meta( $object_id, $this->key );
                break;
            case 'option':
                delete_option( $this->key );
                break;
            case 'site':
                delete_site_option( $this->key );
                break;
            default:
                delete_post_meta( $object_id, $this->key );
        }
    }
}
