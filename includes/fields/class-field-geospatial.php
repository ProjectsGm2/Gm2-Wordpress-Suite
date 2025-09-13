<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GM2_Field_Geospatial extends GM2_Field {
    private static $assets_hooked = false;

    public function __construct( $key, $args = array() ) {
        parent::__construct( $key, $args );

        if ( ! self::$assets_hooked ) {
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
            add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
            self::$assets_hooked = true;
        }
    }

    public static function enqueue_assets() {
        wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
        wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );

        if ( defined( 'GM2_PLUGIN_URL' ) ) {
            wp_enqueue_style( 'gm2-geospatial', GM2_PLUGIN_URL . 'admin/css/gm2-geospatial.css', array(), defined( 'GM2_VERSION' ) ? GM2_VERSION : false );
            wp_enqueue_script( 'gm2-geospatial', GM2_PLUGIN_URL . 'admin/js/gm2-geospatial.js', array( 'leaflet' ), defined( 'GM2_VERSION' ) ? GM2_VERSION : false, true );
        }
    }

    protected function render_field( $value, $object_id, $context_type, $placeholder = '' ) {
        $value   = is_array( $value ) ? $value : array();
        $lat     = $value['lat'] ?? '';
        $lng     = $value['lng'] ?? '';
        $address = $value['address'] ?? array();
        $disabled = disabled( $this->args['disabled'] ?? false, true, false );
        $placeholder_attr = $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';

        if ( 'public' === $context_type ) {
            echo '<div class="gm2-geo-address">' . esc_html( self::format_address( $address ) ) . '</div>';
            return;
        }

        echo '<div class="gm2-geo-field">';
        echo '<div id="' . esc_attr( $this->key ) . '-map" class="gm2-geo-map"></div>';
        echo '<input type="hidden" name="' . esc_attr( $this->key ) . '[lat]" value="' . esc_attr( $lat ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<input type="hidden" name="' . esc_attr( $this->key ) . '[lng]" value="' . esc_attr( $lng ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<input type="hidden" class="gm2-geo-address-data" name="' . esc_attr( $this->key ) . '[address]" value="' . esc_attr( wp_json_encode( $address ) ) . '"' . $disabled . $placeholder_attr . ' />';
        echo '<div class="gm2-geo-address">' . esc_html( self::format_address( $address ) ) . '</div>';
        echo '</div>';
    }

    public function sanitize_field_value( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $lat = isset( $value['lat'] ) ? floatval( $value['lat'] ) : 0;
        $lng = isset( $value['lng'] ) ? floatval( $value['lng'] ) : 0;
        $address = array();

        if ( ! empty( $value['address'] ) ) {
            $addr = is_string( $value['address'] ) ? json_decode( wp_unslash( $value['address'] ), true ) : $value['address'];
            if ( is_array( $addr ) ) {
                $address = array_map( 'sanitize_text_field', $addr );
            }
        }

        return array(
            'lat'     => $lat,
            'lng'     => $lng,
            'address' => $address,
        );
    }

    public static function reverse_geocode( $lat, $lng ) {
        $url = add_query_arg(
            array(
                'format'         => 'jsonv2',
                'lat'            => $lat,
                'lon'            => $lng,
                'addressdetails' => 1,
            ),
            'https://nominatim.openstreetmap.org/reverse'
        );

        $response = wp_remote_get( $url, array( 'headers' => array( 'User-Agent' => 'gm2-wordpress-suite' ) ) );
        if ( is_wp_error( $response ) ) {
            return array();
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['address'] ?? array();
    }

    public static function format_address( $address ) {
        if ( ! is_array( $address ) ) {
            return '';
        }
        $parts = array_filter(
            array(
                $address['road'] ?? '',
                $address['city'] ?? $address['town'] ?? $address['village'] ?? '',
                $address['state'] ?? '',
                $address['postcode'] ?? '',
                $address['country'] ?? '',
            )
        );
        return implode( ', ', $parts );
    }
}
