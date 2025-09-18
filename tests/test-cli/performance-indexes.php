<?php
namespace Gm2;

class MetaIndexCliTestWpdb
{
    public string $postmeta = 'wp_postmeta';
    public string $prefix   = 'wp_';
    public string $last_error = '';
    public array $queries = [];
    public array $existing;

    public function __construct(array $existing = [])
    {
        $this->existing = $existing;
    }

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $query = preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $query, 1);
        }

        return $query;
    }

    public function get_results(string $query, $output = null): array
    {
        $this->queries[] = $query;
        foreach ($this->existing as $name) {
            if (strpos($query, $name) !== false) {
                return [['Key_name' => $name]];
            }
        }

        return [];
    }

    public function query(string $query)
    {
        $this->queries[] = $query;

        if (preg_match('/CREATE INDEX `([^`]+)`/', $query, $matches) === 1) {
            if (!in_array($matches[1], $this->existing, true)) {
                $this->existing[] = $matches[1];
            }
        } elseif (preg_match('/DROP INDEX `([^`]+)`/', $query, $matches) === 1) {
            $this->existing = array_values(array_filter(
                $this->existing,
                static fn(string $name): bool => $name !== $matches[1]
            ));
        }

        return 1;
    }
}
?>
<?php
namespace {

use PHPUnit\Framework\TestCase;

if ( ! defined( 'WP_CLI' ) ) {
    define( 'WP_CLI', true );
}

if ( ! class_exists( 'WP_CLI' ) ) {
    class WP_CLI {
        public static $lines = [];
        public static $confirmations = [];
        public static function error( $msg, $code = 1 ) { throw new \Exception( $msg, $code ); }
        public static function success( $msg ) { self::$lines[] = $msg; }
        public static function warning( $msg ) { self::$lines[] = $msg; }
        public static function line( $msg ) { self::$lines[] = $msg; }
        public static function confirm( $msg, $assoc_args = [] ) { self::$confirmations[] = $msg; }
        public static function add_command( $name, $callable ) {}
    }
}

if ( ! class_exists( 'WP_CLI_Command' ) ) {
    class WP_CLI_Command {}
}

class PerformanceIndexesCliTest extends TestCase
{
    private \Gm2\MetaIndexCliTestWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        WP_CLI::$lines         = [];
        WP_CLI::$confirmations = [];
        $this->setWpdb();

        if ( ! class_exists( '\\Gm2\\Gm2_Performance_CLI' ) ) {
            require __DIR__ . '/../../includes/cli/class-gm2-performance-cli.php';
        }
    }

    private function setWpdb(array $existing = []): void
    {
        $this->wpdb       = new \Gm2\MetaIndexCliTestWpdb( $existing );
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    public function test_list_outputs_status(): void
    {
        $this->setWpdb( ['gm2_meta_price_idx'] );
        $cli = new \Gm2\Gm2_Performance_CLI();
        $cli->list_( [], [] );

        $output = implode( "\n", WP_CLI::$lines );
        $this->assertNotEmpty( $output );
        $this->assertStringContainsString( 'price: exists', $output );
        $this->assertStringContainsString( 'Filters:', end( WP_CLI::$lines ) );
    }

    public function test_create_creates_index_and_confirms(): void
    {
        $cli = new \Gm2\Gm2_Performance_CLI();
        $cli->create( [], [ 'key' => 'start_date', 'yes' => true ] );

        $this->assertContains( 'gm2_meta_start_date_idx', $this->wpdb->existing );
        $this->assertContains( 'Create index gm2_meta_start_date_idx on wp_postmeta?', WP_CLI::$confirmations );
        $this->assertContains( 'Created index gm2_meta_start_date_idx for meta key start_date.', WP_CLI::$lines );
    }

    public function test_drop_warns_when_missing(): void
    {
        $cli = new \Gm2\Gm2_Performance_CLI();
        $cli->drop( [], [ 'key' => 'latitude', 'yes' => true ] );

        $this->assertContains( 'was not present', implode( '\n', WP_CLI::$lines ) );
    }

    public function test_create_with_unknown_key_errors(): void
    {
        $cli = new \Gm2\Gm2_Performance_CLI();

        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( 'Meta key "unknown" is not registered for indexing.' );
        $cli->create( [], [ 'key' => 'unknown', 'yes' => true ] );
    }
}
}
