<?php
/**
 * Optional custom tables and CRUD helpers for large datasets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GM2_CUSTOM_TABLES_VERSION = 2;

/**
 * Determine whether to use custom tables.
 *
 * @return bool
 */
function gm2_use_custom_tables() {
    /**
     * Filter whether custom tables should be used.
     *
     * @param bool $enabled True when custom tables are enabled.
     */
    return (bool) apply_filters( 'gm2_use_custom_tables', get_option( 'gm2_use_custom_tables', false ) );
}

/**
 * Get the name of the fields table.
 *
 * @param bool $force_custom Force returning the custom table name.
 * @return string
 */
function gm2_get_fields_table_name( $force_custom = false ) {
    global $wpdb;
    if ( $force_custom || gm2_use_custom_tables() ) {
        return $wpdb->prefix . 'gm2_fields';
    }
    return $wpdb->postmeta;
}

/**
 * Get the name of the relations table.
 *
 * @param bool $force_custom Force returning the custom table name.
 * @return string
 */
function gm2_get_relations_table_name( $force_custom = false ) {
    global $wpdb;
    if ( $force_custom || gm2_use_custom_tables() ) {
        return $wpdb->prefix . 'gm2_relations';
    }
    return $wpdb->term_relationships;
}

/**
 * Create or update the custom tables if needed.
 *
 * Handles versioned upgrades.
 */
function gm2_custom_tables_maybe_install() {
    $current = (int) get_option( 'gm2_custom_tables_version', 0 );
    if ( $current >= GM2_CUSTOM_TABLES_VERSION ) {
        return;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $fields_table    = gm2_get_fields_table_name( true );
    $relations_table = gm2_get_relations_table_name( true );

    $sql = "CREATE TABLE $fields_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(191) NOT NULL,
        value longtext NULL,
        PRIMARY KEY  (id),
        KEY name (name)
    ) $charset_collate;";
    dbDelta( $sql );

    $sql = "CREATE TABLE $relations_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        object_id bigint(20) unsigned NOT NULL,
        field_id bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (id),
        KEY object_field (object_id, field_id),
        KEY field_id (field_id),
        KEY object_id (object_id)
    ) $charset_collate;";
    dbDelta( $sql );

    update_option( 'gm2_custom_tables_version', GM2_CUSTOM_TABLES_VERSION );
}

/**
 * Create a field row.
 *
 * @param string $name  Field name.
 * @param mixed  $value Field value.
 * @return int Inserted row ID.
 */
function gm2_fields_create( $name, $value ) {
    global $wpdb;
    $table = gm2_get_fields_table_name();
    $name  = sanitize_key( $name );
    $wpdb->insert( $table, [ 'name' => $name, 'value' => maybe_serialize( $value ) ], [ '%s', '%s' ] );
    $id = (int) $wpdb->insert_id;
    wp_cache_delete( "full_$id", 'gm2_fields' );
    wp_cache_delete( "meta_$id", 'gm2_fields' );
    return $id;
}

/**
 * Retrieve a field row by ID.
 *
 * @param int $id Field ID.
 * @return array|null
 */
function gm2_fields_get( $id, $with_value = true ) {
    $cache_key = ( $with_value ? 'full_' : 'meta_' ) . (int) $id;
    $cached    = wp_cache_get( $cache_key, 'gm2_fields' );
    if ( false !== $cached ) {
        return $cached;
    }
    global $wpdb;
    $table   = gm2_get_fields_table_name();
    $select  = $with_value ? '*' : 'id, name';
    $row     = $wpdb->get_row( $wpdb->prepare( "SELECT $select FROM $table WHERE id = %d", $id ), ARRAY_A );
    if ( $with_value && $row && isset( $row['value'] ) ) {
        $row['value'] = maybe_unserialize( $row['value'] );
    }
    wp_cache_set( $cache_key, $row, 'gm2_fields' );
    return $row;
}

/**
 * Update a field row.
 *
 * @param int   $id   Field ID.
 * @param array $data Column => value pairs.
 * @return bool
 */
function gm2_fields_update( $id, $data ) {
    global $wpdb;
    $table = gm2_get_fields_table_name();
    if ( isset( $data['value'] ) ) {
        $data['value'] = maybe_serialize( $data['value'] );
    }
    if ( isset( $data['name'] ) ) {
        $data['name'] = sanitize_key( $data['name'] );
    }
    $updated = false !== $wpdb->update( $table, $data, [ 'id' => $id ] );
    if ( $updated ) {
        wp_cache_delete( "full_$id", 'gm2_fields' );
        wp_cache_delete( "meta_$id", 'gm2_fields' );
    }
    return $updated;
}

/**
 * Delete a field row by ID.
 *
 * @param int $id Field ID.
 * @return bool
 */
function gm2_fields_delete( $id ) {
    global $wpdb;
    $table   = gm2_get_fields_table_name();
    $deleted = false !== $wpdb->delete( $table, [ 'id' => $id ] );
    if ( $deleted ) {
        wp_cache_delete( "full_$id", 'gm2_fields' );
        wp_cache_delete( "meta_$id", 'gm2_fields' );
    }
    return $deleted;
}

/**
 * Create a relation between an object and a field.
 *
 * @param int $object_id Object ID.
 * @param int $field_id  Field ID.
 * @return int Insert ID.
 */
function gm2_relations_create( $object_id, $field_id ) {
    global $wpdb;
    $table = gm2_get_relations_table_name();
    $wpdb->insert( $table, [ 'object_id' => $object_id, 'field_id' => $field_id ], [ '%d', '%d' ] );
    wp_cache_delete( "obj_$object_id", 'gm2_relations' );
    return (int) $wpdb->insert_id;
}

/**
 * Get relations for an object.
 *
 * @param int $object_id Object ID.
 * @return array
 */
function gm2_relations_get_by_object( $object_id ) {
    $cache_key = 'obj_' . (int) $object_id;
    $cached    = wp_cache_get( $cache_key, 'gm2_relations' );
    if ( false !== $cached ) {
        return $cached;
    }
    global $wpdb;
    $table  = gm2_get_relations_table_name();
    $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE object_id = %d", $object_id ), ARRAY_A );
    wp_cache_set( $cache_key, $result, 'gm2_relations' );
    return $result;
}

/**
 * Delete a relation.
 *
 * @param int $object_id Object ID.
 * @param int $field_id  Field ID.
 * @return bool
 */
function gm2_relations_delete( $object_id, $field_id ) {
    global $wpdb;
    $table   = gm2_get_relations_table_name();
    $deleted = false !== $wpdb->delete( $table, [ 'object_id' => $object_id, 'field_id' => $field_id ], [ '%d', '%d' ] );
    if ( $deleted ) {
        wp_cache_delete( "obj_$object_id", 'gm2_relations' );
    }
    return $deleted;
}

/**
 * Rename a field safely.
 *
 * @param string $old Existing field name.
 * @param string $new New field name.
 * @return bool
 */
function gm2_fields_rename( $old, $new ) {
    global $wpdb;
    $table = gm2_get_fields_table_name();
    $old = sanitize_key( $old );
    $new = sanitize_key( $new );
    return false !== $wpdb->update( $table, [ 'name' => $new ], [ 'name' => $old ] );
}

/**
 * Backfill data from core tables into custom tables.
 *
 * @return int Number of rows inserted.
 */
function gm2_custom_tables_backfill() {
    // TODO: Implement copying from core tables such as postmeta.
    return 0;
}
