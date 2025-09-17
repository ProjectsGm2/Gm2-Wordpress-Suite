<?php

namespace Gm2\SEO\Schema;

use Gm2\SEO\Schema\Mapper\MapperInterface;
use WP_Post;
use WP_Query;

class Manager
{
    private static ?self $instance = null;

    /** @var array<string,MapperInterface> */
    private array $mappers = [];

    private bool $printed = false;

    private bool $suppressed = false;

    private bool $registered = false;

    public function __construct(array $mappers = [])
    {
        $this->setMappers($mappers);
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function bootstrap(array $mappers): self
    {
        $instance = self::instance();
        $instance->setMappers($mappers);
        $instance->register();

        return $instance;
    }

    public static function reset(): void
    {
        if (self::$instance instanceof self) {
            self::$instance->unregister();
        }

        self::$instance = null;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        add_action('wp_head', [$this, 'handleHead'], 1);
        add_action('wp_footer', [$this, 'handleFooter'], 1);
        add_filter('gm2_seo_cp_schema', [$this, 'maybeBlockLegacySchema'], 10, 3);
        $this->registered = true;
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        remove_action('wp_head', [$this, 'handleHead'], 1);
        remove_action('wp_footer', [$this, 'handleFooter'], 1);
        remove_filter('gm2_seo_cp_schema', [$this, 'maybeBlockLegacySchema'], 10);
        $this->registered = false;
        $this->printed = false;
        $this->suppressed = false;
    }

    public function setMappers(array $mappers): void
    {
        $this->mappers = [];
        foreach ($mappers as $mapper) {
            if ($mapper instanceof MapperInterface) {
                $this->mappers[$mapper->getPostType()] = $mapper;
            }
        }
    }

    /**
     * @return array<string,MapperInterface>
     */
    public function getMappers(): array
    {
        return $this->mappers;
    }

    public function handleHead(): void
    {
        $this->render('wp_head');
    }

    public function handleFooter(): void
    {
        $this->render('wp_footer');
    }

    public function render(string $hook = ''): void
    {
        if ($this->printed || $this->suppressed) {
            return;
        }

        if (is_admin() || is_feed() || is_404()) {
            return;
        }

        if ($this->shouldBailForThirdParty()) {
            $this->suppressed = true;
            return;
        }

        if (is_singular()) {
            $object = get_queried_object();
            if ($object instanceof WP_Post) {
                $this->renderSingular($object, $hook);
            }

            return;
        }

        if (is_post_type_archive()) {
            $postType = get_query_var('post_type');
            if (is_array($postType)) {
                $postType = reset($postType) ?: '';
            }

            if (!$postType && get_post_type()) {
                $postType = get_post_type();
            }

            if (is_string($postType) && $postType !== '') {
                $this->renderArchive($postType, $hook);
            }
        }
    }

    private function renderSingular(WP_Post $post, string $hook): void
    {
        $mapper = $this->mappers[$post->post_type] ?? null;
        if (!$mapper instanceof MapperInterface) {
            return;
        }

        if (!$this->isEnabled($mapper)) {
            return;
        }

        $result = $mapper->map($post);
        if (is_wp_error($result)) {
            do_action('gm2_seo_schema_validation_error', $result, $post, $mapper);
            return;
        }

        $payload = $result;
        if (!isset($payload['@context'])) {
            $payload['@context'] = 'https://schema.org';
        }

        $payload = apply_filters(
            'gm2_seo_schema_payload',
            $payload,
            [
                'context'   => 'singular',
                'post_id'   => $post->ID,
                'post_type' => $post->post_type,
                'mapper'    => $mapper,
                'hook'      => $hook,
            ]
        );

        if (empty($payload)) {
            return;
        }

        $this->output($payload);
    }

    private function renderArchive(string $postType, string $hook): void
    {
        $mapper = $this->mappers[$postType] ?? null;
        if (!$mapper instanceof MapperInterface) {
            return;
        }

        if (!$this->isEnabled($mapper)) {
            return;
        }

        global $wp_query;
        if (!$wp_query instanceof WP_Query) {
            return;
        }

        $items = [];
        foreach ($wp_query->posts as $index => $post) {
            $postObject = get_post($post);
            if (!$postObject instanceof WP_Post) {
                continue;
            }

            $mapped = $mapper->map($postObject);
            if (is_wp_error($mapped)) {
                do_action('gm2_seo_schema_validation_error', $mapped, $postObject, $mapper);
                continue;
            }

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'url'      => get_permalink($postObject),
                'item'     => $mapped,
            ];
        }

        if (!$items) {
            return;
        }

        $payload = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'url'             => get_post_type_archive_link($postType) ?: '',
            'itemListElement' => $items,
        ];

        $payload = apply_filters(
            'gm2_seo_schema_payload',
            $payload,
            [
                'context'   => 'archive',
                'post_type' => $postType,
                'mapper'    => $mapper,
                'hook'      => $hook,
            ]
        );

        if (empty($payload)) {
            return;
        }

        $this->output($payload);
    }

    private function output(array $payload): void
    {
        echo '<script type="application/ld+json">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        $this->printed = true;
    }

    private function isEnabled(MapperInterface $mapper): bool
    {
        return get_option($mapper->getOptionName(), '1') === '1';
    }

    private function shouldBailForThirdParty(): bool
    {
        if (did_action('wpseo_json_ld_output') > 0 || defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend')) {
            return true;
        }

        if (did_action('rank_math/json_ld') > 0 || defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            return true;
        }

        if (did_action('aioseo_schema_graph') > 0 || defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin')) {
            return true;
        }

        if (did_action('seopress/jsonld/print') > 0 || defined('SEOPRESS_VERSION') || class_exists('SEOPRESS_Core')) {
            return true;
        }

        if (did_action('the_seo_framework_front_schema') > 0 || defined('THE_SEO_FRAMEWORK_VERSION')) {
            return true;
        }

        return (bool) apply_filters('gm2_seo_schema_third_party', false);
    }

    public function maybeBlockLegacySchema($skip, $schema, $context)
    {
        if ($skip) {
            return $skip;
        }

        return $this->printed;
    }
}
