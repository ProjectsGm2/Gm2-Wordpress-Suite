<?php
namespace Gm2;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Audit product posts for required schema fields.
 */
class Gm2_Schema_Audit_CLI extends \WP_CLI_Command {
    /**
     * Run the schema audit.
     */
    public function __invoke( $args, $assoc_args ) {
        $query = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $issues = 0;
        foreach ( $query->posts as $id ) {
            $missing = $this->get_missing_fields( $id );
            if ( $missing ) {
                $issues++;
                \WP_CLI::line( sprintf( __( 'Product %1$d: %2$s', 'gm2-wordpress-suite' ), $id, get_permalink( $id ) ) );
                foreach ( $missing as $field ) {
                    \WP_CLI::line( sprintf( __( '  - Missing %s. %s', 'gm2-wordpress-suite' ), $field, $this->recommendation( $field ) ) );
                }
            }
        }

        if ( $issues ) {
            \WP_CLI::warning( sprintf( __( '%d products have schema issues.', 'gm2-wordpress-suite' ), $issues ) );
        } else {
            \WP_CLI::success( __( 'All products have required schema fields.', 'gm2-wordpress-suite' ) );
        }
    }

    protected function get_missing_fields( $id ) {
        $missing = [];

        $name = get_the_title( $id );
        if ( '' === trim( $name ) ) {
            $missing[] = 'name';
        }

        $price = get_post_meta( $id, 'price', true );
        if ( '' === $price ) {
            $price = get_post_meta( $id, '_price', true );
        }
        if ( '' === $price ) {
            $missing[] = 'price';
        }

        $availability = get_post_meta( $id, 'availability', true );
        if ( '' === $availability ) {
            $availability = get_post_meta( $id, 'stock', true );
        }
        if ( '' === $availability ) {
            $availability = get_post_meta( $id, '_stock_status', true );
        }
        if ( '' === $availability ) {
            $missing[] = 'availability';
        }

        $sku = get_post_meta( $id, 'sku', true );
        if ( '' === $sku ) {
            $sku = get_post_meta( $id, '_sku', true );
        }
        if ( '' === $sku ) {
            $missing[] = 'SKU';
        }

        $brand = get_post_meta( $id, 'brand', true );
        if ( '' === $brand ) {
            $missing[] = 'brand';
        }

        return $missing;
    }

    protected function recommendation( $field ) {
        switch ( $field ) {
            case 'name':
                return __( 'Add a product title.', 'gm2-wordpress-suite' );
            case 'price':
                return __( 'Set a price in product metadata.', 'gm2-wordpress-suite' );
            case 'availability':
                return __( 'Specify stock status or quantity.', 'gm2-wordpress-suite' );
            case 'SKU':
                return __( 'Assign a unique SKU.', 'gm2-wordpress-suite' );
            case 'brand':
                return __( 'Provide a brand name.', 'gm2-wordpress-suite' );
            default:
                return '';
        }
    }
}

\WP_CLI::add_command( 'gm2 schema-audit', __NAMESPACE__ . '\\Gm2_Schema_Audit_CLI' );
