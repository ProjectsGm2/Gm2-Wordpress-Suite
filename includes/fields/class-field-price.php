<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Price extends GM2_Field {
    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
        $currency = esc_html( get_option( 'gm2_currency', 'USD' ) );
        echo '<input type="number" step="0.01" name="' . esc_attr( $this->key ) . '" value="' . esc_attr( $value ) . '" class="gm2-price"' . $disabled . $placeholder_attr . ' />';
        echo ' <span class="gm2-price-currency">' . $currency . '</span>';
    }

    public function sanitize( $value ) {
        $value = filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
        return is_numeric( $value ) ? $value : '';
    }
}
