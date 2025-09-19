<?php

declare(strict_types=1);

namespace Gm2\Presets;

use WP_Error;

use function __;
use function add_filter;
use function apply_filters;
use function array_key_exists;
use function basename;
use function dirname;
use function file_exists;
use function file_get_contents;
use function get_option;
use function glob;
use function gm2_model_import;
use function is_array;
use function is_dir;
use function is_string;
use function is_wp_error;
use function json_decode;
use function rest_validate_value_from_schema;
use function rtrim;
use function sort;
use function sprintf;
use function str_replace;
use function ucwords;

/**
 * Loads, validates, and exposes bundled blueprint presets.
 */
class PresetManager
{
    /**
     * Loaded preset data keyed by slug.
     *
     * @var array<string, array>
     */
    private array $presets = [];

    /**
     * Validation errors keyed by preset slug.
     *
     * @var array<string, WP_Error>
     */
    private array $errors = [];

    /**
     * Blueprint schema definition.
     *
     * @var array<string, mixed>
     */
    private array $schema = [];

    /**
     * Directory map for each preset slug.
     *
     * @var array<string, string>
     */
    private array $paths = [];

    public function __construct(
        private string $directory,
        private ?string $schemaFile = null
    ) {
        $this->directory = rtrim($this->directory, "\\/");
        if ($this->schemaFile === null) {
            $this->schemaFile = $this->directory . '/schema.json';
        }

        $this->loadSchema();
        $this->loadPresets();
    }

    /**
     * Register WordPress filters so other modules can consume preset data.
     */
    public function registerHooks(): void
    {
        add_filter('gm2/presets/manager', [$this, 'filterManager']);
        add_filter('gm2/presets/all', [$this, 'filterAll']);
        add_filter('gm2/presets/blueprint', [$this, 'filterBlueprint'], 10, 2);
        add_filter('gm2/presets/list', [$this, 'filterList']);
        add_filter('gm2/presets/field_groups', [$this, 'filterFieldGroups'], 10, 2);
        add_filter('gm2/presets/relationships', [$this, 'filterRelationships'], 10, 2);
        add_filter('gm2/presets/default_terms', [$this, 'filterDefaultTerms'], 10, 2);
        add_filter('gm2/presets/elementor/queries', [$this, 'filterElementorQueries'], 10, 2);
        add_filter('gm2/presets/elementor/query_ids', [$this, 'filterElementorQueryIds']);
        add_filter('gm2/presets/seo/mappings', [$this, 'filterSeoMappings'], 10, 2);
        add_filter('gm2/presets/templates', [$this, 'filterTemplates'], 10, 2);
        add_filter('gm2/presets/errors', [$this, 'filterErrors']);
    }

    /**
     * Retrieve all loaded presets keyed by slug.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        return $this->presets;
    }

    /**
     * Fetch a single preset blueprint.
     */
    public function get(string $slug): ?array
    {
        return $this->presets[$slug] ?? null;
    }

    /**
     * Return a label/description map for selection UIs.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public function getList(): array
    {
        $list = [];
        foreach ($this->presets as $slug => $preset) {
            $label = '';
            if (array_key_exists('label', $preset) && is_string($preset['label'])) {
                $label = $preset['label'];
            }
            $description = '';
            if (array_key_exists('description', $preset) && is_string($preset['description'])) {
                $description = $preset['description'];
            }
            if ($label === '') {
                $label = ucwords(str_replace(['-', '_'], ' ', $slug));
            }
            $list[$slug] = [
                'label'       => $label,
                'description' => $description,
            ];
        }
        return $list;
    }

    /**
     * Retrieve the directory path for a preset.
     */
    public function getPath(string $slug): ?string
    {
        return $this->paths[$slug] ?? null;
    }

    /**
     * Gather field groups keyed by preset.
     *
     * @return array<string, array>
     */
    public function getFieldGroups(?string $slug = null): array
    {
        $result = [];
        foreach ($this->selectPresets($slug) as $presetSlug => $preset) {
            $groups = $preset['field_groups'] ?? [];
            if (!$groups && isset($preset['fields']['groups'])) {
                $groups = $preset['fields']['groups'];
            }
            if (is_array($groups)) {
                $result[$presetSlug] = $groups;
            }
        }
        return $result;
    }

    /**
     * Gather relationships keyed by preset slug.
     *
     * @return array<string, array>
     */
    public function getRelationships(?string $slug = null): array
    {
        $result = [];
        foreach ($this->selectPresets($slug) as $presetSlug => $preset) {
            if (isset($preset['relationships']) && is_array($preset['relationships'])) {
                $result[$presetSlug] = $preset['relationships'];
            } else {
                $result[$presetSlug] = [];
            }
        }
        return $result;
    }

    /**
     * Retrieve default term definitions keyed by preset.
     *
     * @return array<string, array>
     */
    public function getDefaultTerms(?string $slug = null): array
    {
        $result = [];
        foreach ($this->selectPresets($slug) as $presetSlug => $preset) {
            if (isset($preset['default_terms']) && is_array($preset['default_terms'])) {
                $result[$presetSlug] = $preset['default_terms'];
            } else {
                $result[$presetSlug] = [];
            }
        }
        return $result;
    }

    /**
     * Retrieve Elementor query definitions keyed by preset.
     *
     * @return array<string, array>
     */
    public function getElementorQueries(?string $slug = null): array
    {
        $result = [];
        foreach ($this->selectPresets($slug) as $presetSlug => $preset) {
            $result[$presetSlug] = $this->parseElementorQueries($preset);
        }
        return $result;
    }

    /**
     * Retrieve Elementor query IDs mapped to their definitions.
     *
     * @return array<string, array>
     */
    public function getElementorQueryIds(?string $slug = null): array
    {
        $result = [];
        foreach ($this->selectPresets($slug) as $presetSlug => $preset) {
            $queries = $this->parseElementorQueries($preset);
            foreach ($queries as $key => $definition) {
                if (!is_array($definition)) {
                    continue;
                }
                $id = $definition['id'] ?? $key;
                if (!is_string($id) || $id === '') {
                    continue;
                }
                $result[$id] = $definition;
                $result[$id]['preset'] = $presetSlug;
                $result[$id]['key']    = $key;
            }
        }
        return $result;
    }

    /**
     * Retrieve SEO mappings keyed by preset.
     *
     * @return array<string, array>
     */
    public function getSeoMappings(?string $slug = null): array
    {
        $result = [];
        foreach ($this->selectPresets($slug) as $presetSlug => $preset) {
            $result[$presetSlug] = $this->parseSeoMappings($preset);
        }
        return $result;
    }

    /**
     * Retrieve template metadata keyed by preset.
     *
     * @return array<string, array>
     */
    public function getTemplates(?string $slug = null): array
    {
        $result = [];
        foreach ($this->selectPresets($slug) as $presetSlug => $preset) {
            if (isset($preset['templates']) && is_array($preset['templates'])) {
                $result[$presetSlug] = $preset['templates'];
            } else {
                $result[$presetSlug] = [];
            }
        }
        return $result;
    }

    /**
     * Retrieve validation errors keyed by preset slug.
     *
     * @return array<string, WP_Error>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Validate a blueprint against the schema.
     */
    public function validate(array $data, string $slug = ''): true|WP_Error
    {
        if (!$this->schema) {
            return true;
        }

        $result = rest_validate_value_from_schema($data, $this->schema, 'gm2_preset');
        if (is_wp_error($result)) {
            if ($slug !== '') {
                $result->add_data(['preset' => $slug]);
            }
            return $result;
        }

        return true;
    }

    /**
     * Apply a preset blueprint to the site's stored content definitions.
     */
    public function apply(string $slug, bool $force = false): true|WP_Error
    {
        $blueprint = $this->get($slug);
        if ($blueprint === null) {
            return new WP_Error(
                'gm2_preset_missing',
                sprintf(__('Preset "%s" not found.', 'gm2-wordpress-suite'), $slug)
            );
        }

        $validation = $this->validate($blueprint, $slug);
        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!$force && $this->hasExistingContentDefinitions()) {
            return new WP_Error(
                'gm2_preset_conflict',
                __('Existing content definitions detected. Re-run with --force to overwrite them.', 'gm2-wordpress-suite')
            );
        }

        $result = gm2_model_import($blueprint, 'array');
        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Restore the baseline content definitions bundled with the plugin.
     */
    public function restoreDefaults(): true|WP_Error
    {
        $baseline = $this->getBaselineBlueprint();

        $validation = $this->validate($baseline, 'baseline');
        if (is_wp_error($validation)) {
            return $validation;
        }

        $result = gm2_model_import($baseline, 'array');
        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Provide the manager instance via filter.
     */
    public function filterManager($existing = null): self
    {
        return $this;
    }

    /**
     * Supply all presets when the filter is invoked.
     */
    public function filterAll($value)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->presets;
    }

    /**
     * Provide a single blueprint via filter.
     */
    public function filterBlueprint($preset, string $slug = '')
    {
        if ($preset !== null) {
            return $preset;
        }
        if ($slug === '') {
            return null;
        }
        return $this->get($slug);
    }

    /**
     * Provide preset metadata list via filter.
     */
    public function filterList($value)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getList();
    }

    /**
     * Provide field groups via filter.
     */
    public function filterFieldGroups($value, ?string $slug = null)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getFieldGroups($slug);
    }

    /**
     * Provide relationships via filter.
     */
    public function filterRelationships($value, ?string $slug = null)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getRelationships($slug);
    }

    /**
     * Provide default terms via filter.
     */
    public function filterDefaultTerms($value, ?string $slug = null)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getDefaultTerms($slug);
    }

    /**
     * Provide Elementor queries via filter.
     */
    public function filterElementorQueries($value, ?string $slug = null)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getElementorQueries($slug);
    }

    /**
     * Provide Elementor query IDs via filter.
     */
    public function filterElementorQueryIds($value)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getElementorQueryIds();
    }

    /**
     * Provide SEO mappings via filter.
     */
    public function filterSeoMappings($value, ?string $slug = null)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getSeoMappings($slug);
    }

    /**
     * Provide templates via filter.
     */
    public function filterTemplates($value, ?string $slug = null)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->getTemplates($slug);
    }

    /**
     * Provide errors via filter.
     */
    public function filterErrors($value)
    {
        if (!is_array($value)) {
            $value = [];
        }
        return $value + $this->errors;
    }

    /**
     * Load schema file if available.
     */
    private function loadSchema(): void
    {
        if (!$this->schemaFile || !file_exists($this->schemaFile)) {
            $this->schema = [];
            return;
        }
        $contents = file_get_contents($this->schemaFile);
        if ($contents === false) {
            $this->schema = [];
            return;
        }
        $decoded = json_decode($contents, true);
        if (is_array($decoded)) {
            $this->schema = $decoded;
        } else {
            $this->schema = [];
        }
    }

    /**
     * Discover preset files and validate them.
     */
    private function loadPresets(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $pattern = $this->directory . '/*/blueprint.json';
        $files   = glob($pattern);
        if (!is_array($files)) {
            return;
        }
        sort($files);
        foreach ($files as $file) {
            $slug = basename(dirname($file));
            $this->paths[$slug] = dirname($file);
            $contents = file_get_contents($file);
            if ($contents === false) {
                $this->errors[$slug] = new WP_Error('gm2_preset_unreadable', sprintf('Unable to read preset %s.', $slug));
                continue;
            }
            $data = json_decode($contents, true);
            if (!is_array($data)) {
                $this->errors[$slug] = new WP_Error('gm2_preset_invalid_json', sprintf('Preset %s contains invalid JSON.', $slug));
                continue;
            }
            $validation = $this->validate($data, $slug);
            if (is_wp_error($validation)) {
                $this->errors[$slug] = $validation;
                continue;
            }
            $this->presets[$slug] = $data;
        }
    }

    /**
     * Determine whether stored content definitions already exist.
     */
    private function hasExistingContentDefinitions(): bool
    {
        $config = get_option('gm2_custom_posts_config', []);
        if (!is_array($config)) {
            $config = [];
        }
        if (!empty($config['post_types']) || !empty($config['taxonomies'])) {
            return true;
        }
        if (!empty($config['relationships'])) {
            return true;
        }

        $fieldGroups = get_option('gm2_field_groups', []);
        if (is_array($fieldGroups) && !empty($fieldGroups)) {
            return true;
        }

        $schemaMappings = get_option('gm2_cp_schema_map', []);
        if (is_array($schemaMappings) && !empty($schemaMappings)) {
            return true;
        }

        return false;
    }

    /**
     * Select presets to operate on.
     *
     * @return array<string, array>
     */
    private function selectPresets(?string $slug): array
    {
        if ($slug === null) {
            return $this->presets;
        }
        if (!isset($this->presets[$slug])) {
            return [];
        }
        return [ $slug => $this->presets[$slug] ];
    }

    /**
     * Normalize Elementor query definitions from a preset blueprint.
     *
     * @param array<string, mixed> $preset
     * @return array<string, array>
     */
    private function parseElementorQueries(array $preset): array
    {
        $queries = [];

        if (isset($preset['elementor_query_ids']) && is_array($preset['elementor_query_ids'])) {
            foreach ($preset['elementor_query_ids'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $key = $entry['key'] ?? null;
                if (!is_string($key) || $key === '') {
                    $fallback = $entry['id'] ?? null;
                    $key = is_string($fallback) && $fallback !== '' ? $fallback : null;
                }
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $definition = $entry;
                unset($definition['key']);
                $queries[$key] = $definition;
            }
        }

        if (isset($preset['elementor']['queries']) && is_array($preset['elementor']['queries'])) {
            foreach ($preset['elementor']['queries'] as $key => $definition) {
                if (!is_string($key) || $key === '' || !is_array($definition)) {
                    continue;
                }
                if (!isset($queries[$key])) {
                    $queries[$key] = $definition;
                }
            }
        }

        return $queries;
    }

    /**
     * Normalize SEO mapping definitions from a preset blueprint.
     *
     * @param array<string, mixed> $preset
     * @return array<string, array>
     */
    private function parseSeoMappings(array $preset): array
    {
        $mappings = [];

        if (isset($preset['seo_mappings']) && is_array($preset['seo_mappings'])) {
            foreach ($preset['seo_mappings'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $key = $entry['key'] ?? null;
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $definition = $entry;
                unset($definition['key']);
                $mappings[$key] = $definition;
            }
        }

        if (isset($preset['seo']['mappings']) && is_array($preset['seo']['mappings'])) {
            foreach ($preset['seo']['mappings'] as $key => $definition) {
                if (!is_string($key) || $key === '' || !is_array($definition)) {
                    continue;
                }
                if (!isset($mappings[$key])) {
                    $mappings[$key] = $definition;
                }
            }
        }

        if ($mappings === [] && isset($preset['schema_mappings']) && is_array($preset['schema_mappings'])) {
            foreach ($preset['schema_mappings'] as $key => $definition) {
                if (!is_string($key) || $key === '' || !is_array($definition)) {
                    continue;
                }
                $mappings[$key] = $definition;
            }
        }

        return $mappings;
    }

    /**
     * Retrieve the baseline blueprint used when resetting definitions.
     */
    private function getBaselineBlueprint(): array
    {
        $baseline = [
            'post_types'         => [],
            'taxonomies'         => [],
            'field_groups'       => [],
            'fields'             => [
                'groups' => [],
            ],
            'relationships'      => [],
            'default_terms'      => [],
            'templates'          => [],
            'elementor'          => [
                'queries'   => [],
                'templates' => [],
            ],
            'elementor_query_ids' => [],
            'seo'               => [
                'mappings' => [],
            ],
            'seo_mappings'      => [],
            'schema_mappings'   => [],
        ];

        /**
         * Filter the baseline blueprint used when resetting content definitions.
         *
         * @param array<string, mixed> $baseline
         */
        return apply_filters('gm2/presets/baseline_blueprint', $baseline);
    }
}
