<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Text extends GM2_Field {
    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args, 'text' );
    }

    // Inherits base behavior
}
