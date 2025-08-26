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
                \WP_CLI::line( sprintf( 'Product %d: %s', $id, get_permalink( $id ) ) );
                foreach ( $missing as $field ) {
                    \WP_CLI::line( sprintf( '  - Missing %s. %s', $field, $this->recommendation( $field ) ) );
                }
            }
        }

        if ( $issues ) {
            \WP_CLI::warning( sprintf( '%d products have schema issues.', $issues ) );
        } else {
            \WP_CLI::success( 'All products have required schema fields.' );
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
                return 'Add a product title.';
            case 'price':
                return 'Set a price in product metadata.';
            case 'availability':
                return 'Specify stock status or quantity.';
            case 'SKU':
                return 'Assign a unique SKU.';
            case 'brand':
                return 'Provide a brand name.';
            default:
                return '';
        }
    }
}

\WP_CLI::add_command( 'gm2 schema-audit', __NAMESPACE__ . '\\Gm2_Schema_Audit_CLI' );
