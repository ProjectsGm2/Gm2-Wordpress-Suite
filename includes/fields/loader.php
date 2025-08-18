<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-field-base.php';
require_once __DIR__ . '/class-field-text.php';
require_once __DIR__ . '/class-field-textarea.php';
require_once __DIR__ . '/class-field-number.php';
require_once __DIR__ . '/class-field-email.php';
require_once __DIR__ . '/class-field-url.php';
require_once __DIR__ . '/class-field-phone.php';
require_once __DIR__ . '/class-field-select.php';
require_once __DIR__ . '/class-field-media.php';
require_once __DIR__ . '/class-field-file.php';
require_once __DIR__ . '/class-field-audio.php';
require_once __DIR__ . '/class-field-video.php';
require_once __DIR__ . '/class-field-gallery.php';
require_once __DIR__ . '/class-field-checkbox.php';
require_once __DIR__ . '/class-field-radio.php';
require_once __DIR__ . '/class-field-date.php';
require_once __DIR__ . '/class-field-time.php';
require_once __DIR__ . '/class-field-datetime.php';
require_once __DIR__ . '/class-field-daterange.php';
require_once __DIR__ . '/class-field-wysiwyg.php';
require_once __DIR__ . '/class-field-repeater.php';
require_once __DIR__ . '/class-field-flexible.php';
require_once __DIR__ . '/class-field-group.php';
require_once __DIR__ . '/class-field-relationship.php';
require_once __DIR__ . '/class-field-geospatial.php';
require_once __DIR__ . '/class-field-commerce.php';
require_once __DIR__ . '/class-field-price.php';
require_once __DIR__ . '/class-field-stock.php';
require_once __DIR__ . '/class-field-sku.php';
require_once __DIR__ . '/class-field-design.php';
require_once __DIR__ . '/class-field-computed.php';
require_once __DIR__ . '/class-field-message.php';
require_once __DIR__ . '/class-field-toggle.php';
require_once __DIR__ . '/class-field-markdown.php';
require_once __DIR__ . '/class-field-code.php';
require_once __DIR__ . '/class-field-oembed.php';
require_once __DIR__ . '/class-field-gradient.php';
require_once __DIR__ . '/class-field-icon.php';
require_once __DIR__ . '/class-field-badge.php';
require_once __DIR__ . '/class-field-rating.php';

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
    gm2_register_field_type( 'email', 'GM2_Field_Email' );
    gm2_register_field_type( 'url', 'GM2_Field_Url' );
    gm2_register_field_type( 'phone', 'GM2_Field_Phone' );
    gm2_register_field_type( 'select', 'GM2_Field_Select' );
    gm2_register_field_type( 'media', 'GM2_Field_Media' );
    gm2_register_field_type( 'file', 'GM2_Field_File' );
    gm2_register_field_type( 'audio', 'GM2_Field_Audio' );
    gm2_register_field_type( 'video', 'GM2_Field_Video' );
    gm2_register_field_type( 'gallery', 'GM2_Field_Gallery' );
    gm2_register_field_type( 'checkbox', 'GM2_Field_Checkbox' );
    gm2_register_field_type( 'radio', 'GM2_Field_Radio' );
    gm2_register_field_type( 'date', 'GM2_Field_Date' );
    gm2_register_field_type( 'time', 'GM2_Field_Time' );
    gm2_register_field_type( 'datetime', 'GM2_Field_Datetime' );
    gm2_register_field_type( 'daterange', 'GM2_Field_Daterange' );
    gm2_register_field_type( 'wysiwyg', 'GM2_Field_Wysiwyg' );
    gm2_register_field_type( 'repeater', 'GM2_Field_Repeater' );
    gm2_register_field_type( 'flexible', 'GM2_Field_Flexible' );
    gm2_register_field_type( 'group', 'GM2_Field_Group' );
    gm2_register_field_type( 'relationship', 'GM2_Field_Relationship' );
    gm2_register_field_type( 'geo', 'GM2_Field_Geospatial' );
    gm2_register_field_type( 'commerce', 'GM2_Field_Commerce' );
    gm2_register_field_type( 'price', 'GM2_Field_Price' );
    gm2_register_field_type( 'stock', 'GM2_Field_Stock' );
    gm2_register_field_type( 'sku', 'GM2_Field_Sku' );
    gm2_register_field_type( 'design', 'GM2_Field_Design' );
    gm2_register_field_type( 'computed', 'GM2_Field_Computed' );
    gm2_register_field_type( 'message', 'GM2_Field_Message' );
    gm2_register_field_type( 'toggle', 'GM2_Field_Toggle' );
    gm2_register_field_type( 'markdown', 'GM2_Field_Markdown' );
    gm2_register_field_type( 'code', 'GM2_Field_Code' );
    gm2_register_field_type( 'oembed', 'GM2_Field_Oembed' );
    gm2_register_field_type( 'gradient', 'GM2_Field_Gradient' );
    gm2_register_field_type( 'icon', 'GM2_Field_Icon' );
    gm2_register_field_type( 'badge', 'GM2_Field_Badge' );
    gm2_register_field_type( 'rating', 'GM2_Field_Rating' );
}
add_action( 'init', 'gm2_register_default_field_types' );
