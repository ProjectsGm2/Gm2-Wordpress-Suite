<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Message extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type ) {
        $disabled = $this->args['disabled'] ?? false ? ' data-disabled="1"' : '';
        echo '<div class="gm2-field-message"' . $disabled . '>' . esc_html( $this->args['message'] ?? '' ) . '</div>';
    }

    public function save( $object_id, $value, $context_type = 'post' ) {
        // No data saved for message fields.
    }
}
