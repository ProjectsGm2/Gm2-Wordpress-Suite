<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Markdown extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        if ( $context_type === 'public' ) {
            echo gm2_render_markdown( (string) $value );
            return;
        }
        $id       = $this->key;
        $settings = wp_enqueue_code_editor( array( 'file' => 'field.md' ) );
        echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $this->key ) . '">' . esc_textarea( $value ) . '</textarea>';
        if ( $settings ) {
            $json = wp_json_encode( $settings );
            echo '<script>jQuery(function(){wp.codeEditor.initialize("' . esc_js( $id ) . '",' . $json . ');});</script>';
        }
    }

    public function sanitize( $value ) {
        return wp_kses_post( $value );
    }
}
