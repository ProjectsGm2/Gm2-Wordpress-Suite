<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-field-base.php';
require_once __DIR__ . '/class-field-text.php';
require_once __DIR__ . '/class-field-textarea.php';
require_once __DIR__ . '/class-field-number.php';
require_once __DIR__ . '/class-field-select.php';
require_once __DIR__ . '/class-field-media.php';
require_once __DIR__ . '/class-field-checkbox.php';
require_once __DIR__ . '/class-field-radio.php';
require_once __DIR__ . '/class-field-date.php';
require_once __DIR__ . '/class-field-wysiwyg.php';
require_once __DIR__ . '/class-field-repeater.php';
require_once __DIR__ . '/class-field-group.php';
require_once __DIR__ . '/class-field-relationship.php';
require_once __DIR__ . '/class-field-geospatial.php';
require_once __DIR__ . '/class-field-commerce.php';
require_once __DIR__ . '/class-field-design.php';
require_once __DIR__ . '/class-field-computed.php';
require_once __DIR__ . '/class-field-message.php';

$gm2_field_types = array();

function gm2_register_field_type( $type, $class ) {
    global $gm2_field_types;
    $gm2_field_types[ $type ] = $class;
}

function gm2_get_field_type_class( $type ) {
    global $gm2_field_types;
    return $gm2_field_types[ $type ] ?? null;
}

function gm2_register_default_field_types() {
    gm2_register_field_type( 'text', 'GM2_Field_Text' );
    gm2_register_field_type( 'textarea', 'GM2_Field_Textarea' );
    gm2_register_field_type( 'number', 'GM2_Field_Number' );
    gm2_register_field_type( 'select', 'GM2_Field_Select' );
    gm2_register_field_type( 'media', 'GM2_Field_Media' );
    gm2_register_field_type( 'checkbox', 'GM2_Field_Checkbox' );
    gm2_register_field_type( 'radio', 'GM2_Field_Radio' );
    gm2_register_field_type( 'date', 'GM2_Field_Date' );
    gm2_register_field_type( 'wysiwyg', 'GM2_Field_Wysiwyg' );
    gm2_register_field_type( 'repeater', 'GM2_Field_Repeater' );
    gm2_register_field_type( 'group', 'GM2_Field_Group' );
    gm2_register_field_type( 'relationship', 'GM2_Field_Relationship' );
    gm2_register_field_type( 'geo', 'GM2_Field_Geospatial' );
    gm2_register_field_type( 'commerce', 'GM2_Field_Commerce' );
    gm2_register_field_type( 'design', 'GM2_Field_Design' );
    gm2_register_field_type( 'computed', 'GM2_Field_Computed' );
    gm2_register_field_type( 'message', 'GM2_Field_Message' );
}
add_action( 'init', 'gm2_register_default_field_types' );
