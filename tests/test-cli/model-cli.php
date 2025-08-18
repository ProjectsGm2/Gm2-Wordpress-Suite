<?php
// Minimal stubs to exercise CLI commands without a full WordPress install.

define( 'WP_CLI', true );
define( 'GM2_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );

class WP_CLI { 
    public static function error( $msg ) { throw new Exception( $msg ); }
    public static function success( $msg ) { echo $msg, "\n"; }
    public static function warning( $msg ) { echo $msg, "\n"; }
    public static function line( $msg ) { echo $msg, "\n"; }
    public static function add_command( $name, $callable ) {}
}
class WP_CLI_Command {}

// Option storage.
$GLOBALS['gm2_options'] = [];
function get_option( $name, $default = [] ) { return $GLOBALS['gm2_options'][$name] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['gm2_options'][$name] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['gm2_options'][$name] ); return true; }

// Storage for seeded content.
$GLOBALS['gm2_seeded_posts'] = [];
$GLOBALS['gm2_seeded_terms'] = [];
$GLOBALS['gm2_seeded_media'] = [];
function wp_insert_post( $data ) { $GLOBALS['gm2_seeded_posts'][] = $data; return count( $GLOBALS['gm2_seeded_posts'] ); }
function wp_insert_term( $name, $taxonomy, $args = [] ) { $GLOBALS['gm2_seeded_terms'][] = [ 'taxonomy' => $taxonomy, 'name' => $name ]; return [ 'term_id' => count( $GLOBALS['gm2_seeded_terms'] ) ]; }
function wp_insert_attachment( $data, $file = '', $parent = 0 ) { $GLOBALS['gm2_seeded_media'][] = $data; return count( $GLOBALS['gm2_seeded_media'] ); }

function gm2_run_model_migrations( $slug, $from, $to ) {
    echo "migrate {$slug} {$from}->{$to}\n";
}

require dirname( __DIR__, 2 ) . '/includes/cli/class-gm2-model.php';

$cli = new \Gm2\Gm2_Model_CLI();

// CPT lifecycle.
$cli->cpt_create( ['book'], ['args' => '{"label":"Books"}'] );
$cli->cpt_update( ['book'], ['args' => '{"public":false}', 'version' => 2] );

// Taxonomy lifecycle.
$cli->taxonomy_create( ['book','genre'], [] );
$cli->taxonomy_update( ['book','genre'], ['args' => '{"hierarchical":true}'] );

// Field lifecycle.
$cli->field_create( ['book','isbn'], ['args' => '{"type":"string"}'] );
$cli->field_update( ['book','isbn'], ['args' => '{"description":"ISBN"}'] );
$cli->field_delete( ['book','isbn'], [] );

$models = get_option( 'gm2_models' );
if ( empty( $models ) || $models[0]['slug'] !== 'book' ) {
    throw new Exception( 'CPT not created as expected.' );
}
if ( empty( $models[0]['taxonomies'] ) || $models[0]['taxonomies'][0]['slug'] !== 'genre' ) {
    throw new Exception( 'Taxonomy not created.' );
}
if ( ! empty( $models[0]['fields'] ) ) {
    throw new Exception( 'Field delete failed.' );
}

// Seeding tests.
$GLOBALS['gm2_seeded_posts'] = [];
$GLOBALS['gm2_seeded_terms'] = [];
$GLOBALS['gm2_seeded_media'] = [];
$cli->seed( [], ['qty' => 2] );
if ( count( $GLOBALS['gm2_seeded_posts'] ) !== 2 || count( $GLOBALS['gm2_seeded_terms'] ) !== 2 ) {
    throw new Exception( 'Seed default quantity failed.' );
}

$GLOBALS['gm2_seeded_posts'] = [];
$GLOBALS['gm2_seeded_terms'] = [];
$cli->seed( [], ['qty' => 1, 'cpt' => 'book'] );
foreach ( $GLOBALS['gm2_seeded_posts'] as $p ) {
    if ( $p['post_type'] !== 'book' ) {
        throw new Exception( 'CPT filter failed.' );
    }
}

$GLOBALS['gm2_seeded_posts'] = [];
$GLOBALS['gm2_seeded_terms'] = [];
$cli->seed( [], ['qty' => 1, 'taxonomy' => 'genre'] );
foreach ( $GLOBALS['gm2_seeded_terms'] as $t ) {
    if ( $t['taxonomy'] !== 'genre' ) {
        throw new Exception( 'Taxonomy filter failed.' );
    }
}

$GLOBALS['gm2_seeded_posts'] = [];
$GLOBALS['gm2_seeded_terms'] = [];
$GLOBALS['gm2_seeded_media'] = [];
$cli->seed( [], ['qty' => 0, 'media' => 3] );
if ( count( $GLOBALS['gm2_seeded_media'] ) !== 3 ) {
    throw new Exception( 'Media seeding failed.' );
}

// Cleanup.
$cli->taxonomy_delete( ['book','genre'], [] );
$cli->cpt_delete( ['book'], [] );

if ( ! empty( get_option( 'gm2_models' ) ) ) {
    throw new Exception( 'Model delete failed.' );
}

echo "CLI tests completed\n";
