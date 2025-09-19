<?php

declare(strict_types=1);

namespace Gm2\Presets;

use WP_Error;

use function array_values;
use function function_exists;
use function get_option;
use function is_array;
use function is_string;
use function json_decode;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function update_option;
use function wp_json_encode;
use function is_wp_error;

/**
 * Handles serialization and deserialization of blueprint data.
 */
class BlueprintIO
{
    private const OPTION_CONFIG = 'gm2_custom_posts_config';
    private const OPTION_FIELD_GROUPS = 'gm2_field_groups';
    private const OPTION_SCHEMA_MAP = 'gm2_cp_schema_map';
    private const OPTION_META = 'gm2_model_blueprint_meta';

    /**
     * Paths that should always be encoded as objects.
     *
     * @var array<int, array<int, string>>
     */
    private const FORCE_OBJECT_PATHS = [
        ['post_types'],
        ['taxonomies'],
        ['default_terms'],
        ['schema_mappings'],
        ['templates'],
        ['fields'],
        ['fields', 'groups', '*'],
        ['elementor'],
        ['elementor', 'queries'],
        ['elementor', 'templates'],
        ['seo'],
        ['seo', 'mappings'],
    ];

    /**
     * Export the complete blueprint definition.
     */
    public static function exportBlueprint(): array
    {
        $config = self::getOptionArray(self::OPTION_CONFIG);
        $postTypes = self::deepConvert($config['post_types'] ?? []);
        $taxonomies = self::deepConvert($config['taxonomies'] ?? []);

        $fieldGroups = self::buildFieldGroupList();

        $defaultTerms = [];
        foreach ($taxonomies as $slug => $definition) {
            if (is_array($definition) && !empty($definition['default_terms']) && is_array($definition['default_terms'])) {
                $defaultTerms[$slug] = self::deepConvert($definition['default_terms']);
            }
        }

        $relationshipMap = self::getRelationshipMap();
        $relationships = array_values($relationshipMap);

        $meta = self::getMeta();
        if (!$relationships) {
            $relationships = array_values(array_map([self::class, 'deepConvert'], $meta['relationships'] ?? []));
        }
        $templates = self::deepConvert($meta['templates'] ?? []);
        $elementorMeta      = self::normalizeElementor($meta['elementor'] ?? []);
        $elementorQueries   = self::convertElementorQueriesToList($elementorMeta['queries'] ?? []);
        $elementorTemplates = $elementorMeta['templates'] ?? [];

        $schemaMappings = self::getOptionArray(self::OPTION_SCHEMA_MAP);
        $seoMappings   = self::convertSchemaMappingsToList($schemaMappings);

        return [
            'post_types'      => $postTypes,
            'taxonomies'      => $taxonomies,
            'field_groups'    => $fieldGroups,
            'fields'          => [
                'groups' => $fieldGroups,
            ],
            'relationships'   => $relationships,
            'default_terms'   => $defaultTerms,
            'elementor_query_ids' => $elementorQueries,
            'elementor'       => [
                'queries'   => $elementorMeta['queries'] ?? [],
                'templates' => $elementorTemplates,
            ],
            'seo_mappings'   => $seoMappings,
            'seo'             => [
                'mappings' => $schemaMappings,
            ],
            'templates'       => $templates,
            'schema_mappings' => $schemaMappings,
        ];
    }

    /**
     * Export only field groups, leaving other blueprint sections empty.
     *
     * @param array<int, string>|null $slugs Optional list of group slugs to export.
     */
    public static function exportFieldGroups(?array $slugs = null): array
    {
        $groups = self::buildFieldGroupList($slugs);

        return [
            'post_types'      => [],
            'taxonomies'      => [],
            'field_groups'    => $groups,
            'fields'          => [
                'groups' => $groups,
            ],
            'relationships'   => [],
            'default_terms'   => [],
            'elementor_query_ids' => [],
            'elementor'       => [
                'queries'   => [],
                'templates' => [],
            ],
            'seo_mappings'   => [],
            'seo'             => [
                'mappings' => [],
            ],
            'templates'       => [],
            'schema_mappings' => [],
        ];
    }

    /**
     * Import a full blueprint definition, replacing stored options.
     */
    public static function importBlueprint(array $data): true|WP_Error
    {
        $data = self::deepConvert($data);

        $postTypes = is_array($data['post_types'] ?? null) ? $data['post_types'] : [];
        $taxonomies = is_array($data['taxonomies'] ?? null) ? $data['taxonomies'] : [];

        $relationships = is_array($data['relationships'] ?? null) ? $data['relationships'] : [];
        $relationshipMap = self::convertRelationshipsToOptionMap($relationships);

        $config = self::getOptionArray(self::OPTION_CONFIG);
        $config['post_types'] = $postTypes;
        $config['taxonomies'] = $taxonomies;
        $config['relationships'] = $relationshipMap;
        update_option(self::OPTION_CONFIG, $config);

        $fieldGroups = self::prepareFieldGroupsForStorage($data);
        update_option(self::OPTION_FIELD_GROUPS, $fieldGroups);

        $schemaMappings = self::resolveSchemaMappings($data);
        update_option(self::OPTION_SCHEMA_MAP, $schemaMappings);

        $meta = self::getMeta();
        $meta['relationships'] = array_values($relationshipMap);
        $meta['templates'] = self::deepConvert($data['templates'] ?? []);
        $elementorBlueprint = [
            'queries'   => self::resolveElementorQueriesFromData($data),
            'templates' => self::extractElementorTemplatesFromData($data),
        ];
        $meta['elementor'] = self::normalizeElementor($elementorBlueprint);
        update_option(self::OPTION_META, $meta);

        return true;
    }

    /**
     * Import field groups, optionally merging with existing groups.
     */
    public static function importFieldGroups(array $data, bool $merge = true): true|WP_Error
    {
        $groups = self::extractFieldGroupList($data);
        if (empty($groups)) {
            return new WP_Error('gm2_field_groups_empty', 'No field groups were provided.');
        }

        $validationBlueprint = self::exportFieldGroups();
        $validationBlueprint['field_groups'] = $groups;
        $validationBlueprint['fields']['groups'] = $groups;
        $validationResult = \gm2_validate_blueprint($validationBlueprint);
        if (is_wp_error($validationResult)) {
            return $validationResult;
        }

        $prepared = self::fieldGroupListToOptionMap($groups);

        if ($merge) {
            $current = self::getOptionArray(self::OPTION_FIELD_GROUPS);
            $prepared = $current + $prepared;
        }

        update_option(self::OPTION_FIELD_GROUPS, $prepared);

        return true;
    }

    /**
     * Convert a blueprint field group definition to the stored option format.
     */
    public static function prepareFieldGroupsForStorage(array $data): array
    {
        $groups = self::extractFieldGroupList($data);
        return self::fieldGroupListToOptionMap($groups);
    }

    /**
     * Encode blueprint data to JSON or YAML.
     */
    public static function encode(array $data, string $format)
    {
        $prepared = self::prepareForEncoding($data);

        switch ($format) {
            case 'yaml':
                if (!function_exists('yaml_emit')) {
                    return new WP_Error('yaml_unavailable', 'YAML support is not available.');
                }
                return yaml_emit($prepared);
            case 'json':
            default:
                $encoder = function_exists('wp_json_encode') ? 'wp_json_encode' : 'json_encode';
                $encoded = $encoder($prepared, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($encoded === false) {
                    return new WP_Error('json_encode_failed', 'Could not encode blueprint to JSON.');
                }
                return $encoded;
        }
    }

    /**
     * Decode blueprint data from the given format.
     *
     * @param array|string $input Raw input or decoded array.
     */
    public static function decode($input, string $format)
    {
        if ($format === 'array') {
            if (!is_array($input)) {
                return new WP_Error('invalid_data', 'Invalid blueprint data.');
            }
            return self::deepConvert($input);
        }

        if (!is_string($input)) {
            return new WP_Error('invalid_data', 'Invalid blueprint data.');
        }

        if ($format === 'yaml') {
            if (!function_exists('yaml_parse')) {
                return new WP_Error('yaml_unavailable', 'YAML support is not available.');
            }
            $parsed = yaml_parse($input);
        } else {
            $parsed = json_decode($input, true);
        }

        if (!is_array($parsed)) {
            return new WP_Error('invalid_data', 'Invalid blueprint data.');
        }

        return self::deepConvert($parsed);
    }

    /**
     * Prepare blueprint data for schema validation.
     */
    public static function prepareForSchema(array $data)
    {
        $encoded = wp_json_encode(self::prepareForEncoding($data));
        if (!is_string($encoded)) {
            return $data;
        }

        $decoded = json_decode($encoded);
        return $decoded ?? $data;
    }

    /**
     * Retrieve blueprint meta storage.
     */
    private static function getMeta(): array
    {
        $meta = get_option(self::OPTION_META, []);
        return is_array($meta) ? self::deepConvert($meta) : [];
    }

    /**
     * Build the list of field groups for export.
     *
     * @param array<int, string>|null $slugs
     * @return array<int, array<string, mixed>>
     */
    private static function buildFieldGroupList(?array $slugs = null): array
    {
        $groups = self::getOptionArray(self::OPTION_FIELD_GROUPS);

        $allowed = null;
        if ($slugs !== null) {
            $allowed = [];
            foreach ($slugs as $slug) {
                if (is_string($slug) && $slug !== '') {
                    $allowed[sanitize_key($slug)] = true;
                }
            }
        }

        $result = [];
        foreach ($groups as $slug => $group) {
            if (!is_array($group)) {
                continue;
            }
            if ($allowed !== null && !isset($allowed[sanitize_key((string) $slug)])) {
                continue;
            }
            $slugString = is_string($slug) ? $slug : (string) $slug;
            $result[] = self::convertGroupForBlueprint($slugString, $group);
        }

        return $result;
    }

    /**
     * Convert a stored field group to blueprint format.
     */
    private static function convertGroupForBlueprint(string $slug, array $group): array
    {
        $fields = [];
        foreach (($group['fields'] ?? []) as $metaKey => $field) {
            if (!is_array($field)) {
                continue;
            }
            $meta = is_string($metaKey) ? $metaKey : (string) $metaKey;
            $fieldData = self::deepConvert($field);
            $fieldData['name'] = is_string($fieldData['name'] ?? '') && $fieldData['name'] !== ''
                ? $fieldData['name']
                : $meta;
            if (empty($fieldData['key']) || !is_string($fieldData['key'])) {
                $fieldData['key'] = self::generateFieldKey($slug, $meta);
            }
            $fields[] = $fieldData;
        }

        return [
            'key'      => is_string($group['key'] ?? '') ? $group['key'] : $slug,
            'title'    => is_string($group['title'] ?? '') ? $group['title'] : $slug,
            'scope'    => is_string($group['scope'] ?? '') ? $group['scope'] : 'post_type',
            'objects'  => self::sanitizeStringArray($group['objects'] ?? []),
            'location' => self::deepConvert($group['location'] ?? []),
            'fields'   => $fields,
        ];
    }

    /**
     * Extract field groups from blueprint data.
     *
     * @param array<string, mixed>|array<int, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private static function extractFieldGroupList(array $data): array
    {
        if (isset($data['field_groups'])) {
            $groups = $data['field_groups'];
        } elseif (isset($data['fields']['groups'])) {
            $groups = $data['fields']['groups'];
        } else {
            $groups = $data;
        }

        if (!is_array($groups)) {
            return [];
        }

        if (self::isAssoc($groups)) {
            $list = [];
            foreach ($groups as $key => $group) {
                if (!is_array($group)) {
                    continue;
                }
                $group['key'] = $group['key'] ?? (is_string($key) ? $key : (string) $key);
                $list[] = self::deepConvert($group);
            }
            return $list;
        }

        $result = [];
        foreach ($groups as $group) {
            if (is_array($group)) {
                $result[] = self::deepConvert($group);
            }
        }
        return $result;
    }

    /**
     * Convert a field group list to the option map format.
     *
     * @param array<int, array<string, mixed>> $groups
     */
    private static function fieldGroupListToOptionMap(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $slug = self::determineGroupSlug($group);
            if ($slug === '') {
                continue;
            }
            $title = is_string($group['title'] ?? '') ? $group['title'] : $slug;
            $scope = is_string($group['scope'] ?? '') ? $group['scope'] : 'post_type';
            $objects = self::sanitizeStringArray($group['objects'] ?? []);
            $location = is_array($group['location'] ?? null) ? self::deepConvert($group['location']) : [];

            $fields = [];
            foreach (($group['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $field = self::deepConvert($field);
                $metaKey = self::determineFieldMetaKey($field);
                if ($metaKey === '') {
                    continue;
                }
                unset($field['key'], $field['name']);
                $fields[$metaKey] = $field;
            }

            $result[$slug] = [
                'title'    => $title,
                'scope'    => $scope,
                'objects'  => $objects,
                'location' => $location,
                'fields'   => $fields,
            ];
        }
        return $result;
    }

    /**
     * Retrieve relationships stored in the config as a normalized map.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function getRelationshipMap(): array
    {
        $config = self::getOptionArray(self::OPTION_CONFIG);
        $relationships = $config['relationships'] ?? [];
        if (!is_array($relationships)) {
            return [];
        }

        $map = [];
        foreach ($relationships as $key => $relationship) {
            if (!is_array($relationship)) {
                continue;
            }
            if (!isset($relationship['type']) && is_string($key)) {
                $relationship['type'] = $key;
            }
            $definition = self::sanitizeRelationshipDefinition($relationship);
            if ($definition === null) {
                continue;
            }
            $map[$definition['type']] = $definition;
        }

        return $map;
    }

    /**
     * Convert relationship definitions from blueprint data to the option map format.
     *
     * @param array<int|string, mixed> $relationships
     * @return array<string, array<string, mixed>>
     */
    private static function convertRelationshipsToOptionMap(array $relationships): array
    {
        $map = [];
        foreach ($relationships as $key => $relationship) {
            if (!is_array($relationship)) {
                continue;
            }
            if (!isset($relationship['type']) && is_string($key)) {
                $relationship['type'] = $key;
            }
            $definition = self::sanitizeRelationshipDefinition($relationship);
            if ($definition === null) {
                continue;
            }
            $map[$definition['type']] = $definition;
        }

        return $map;
    }

    /**
     * Sanitize a relationship definition.
     */
    private static function sanitizeRelationshipDefinition(array $relationship): ?array
    {
        $type = sanitize_key($relationship['type'] ?? $relationship['key'] ?? '');
        $from = sanitize_key($relationship['from'] ?? '');
        $to   = sanitize_key($relationship['to'] ?? '');
        if ($type === '' || $from === '' || $to === '') {
            return null;
        }

        $definition = [
            'type' => $type,
            'from' => $from,
            'to'   => $to,
        ];

        if (!empty($relationship['direction'])) {
            $direction = sanitize_key($relationship['direction']);
            if ($direction !== '') {
                $definition['direction'] = $direction;
            }
        }

        if (isset($relationship['label'])) {
            $label = sanitize_text_field($relationship['label']);
            if ($label !== '') {
                $definition['label'] = $label;
            }
        }

        if (isset($relationship['reverse_label'])) {
            $reverse = sanitize_text_field($relationship['reverse_label']);
            if ($reverse !== '') {
                $definition['reverse_label'] = $reverse;
            }
        }

        if (isset($relationship['cardinality'])) {
            $cardinality = sanitize_text_field($relationship['cardinality']);
            if ($cardinality !== '') {
                $definition['cardinality'] = $cardinality;
            }
        }

        if (isset($relationship['description'])) {
            $description = sanitize_textarea_field($relationship['description']);
            if ($description !== '') {
                $definition['description'] = $description;
            }
        }

        return $definition;
    }

    /**
     * Determine the slug for a field group import.
     */
    private static function determineGroupSlug(array $group): string
    {
        $candidates = [];
        if (isset($group['slug']) && is_string($group['slug'])) {
            $candidates[] = $group['slug'];
        }
        if (isset($group['key']) && is_string($group['key'])) {
            $candidates[] = $group['key'];
        }

        foreach ($candidates as $candidate) {
            $slug = sanitize_key($candidate);
            if ($slug !== '') {
                return $slug;
            }
        }

        return '';
    }

    /**
     * Determine the meta key for a field import.
     */
    private static function determineFieldMetaKey(array $field): string
    {
        $candidates = [];
        if (isset($field['name']) && is_string($field['name'])) {
            $candidates[] = $field['name'];
        }
        if (isset($field['key']) && is_string($field['key'])) {
            $candidates[] = $field['key'];
        }

        foreach ($candidates as $candidate) {
            $key = sanitize_key($candidate);
            if ($key !== '') {
                return $key;
            }
        }

        return '';
    }

    /**
     * Generate a fallback field key when missing from stored data.
     */
    private static function generateFieldKey(string $groupSlug, string $fieldSlug): string
    {
        $group = sanitize_key($groupSlug);
        $field = sanitize_key($fieldSlug);
        if ($group === '') {
            return 'field_' . $field;
        }
        if ($field === '') {
            return 'field_' . $group;
        }
        return 'field_' . $group . '_' . $field;
    }

    /**
     * Resolve schema mappings from blueprint data.
     */
    private static function resolveSchemaMappings(array $data): array
    {
        if (!empty($data['schema_mappings']) && is_array($data['schema_mappings'])) {
            return self::deepConvert($data['schema_mappings']);
        }
        $fromList = self::seoMappingListToMap($data['seo_mappings'] ?? []);
        if ($fromList !== []) {
            return $fromList;
        }
        if (!empty($data['seo']['mappings']) && is_array($data['seo']['mappings'])) {
            return self::deepConvert($data['seo']['mappings']);
        }
        return [];
    }

    /**
     * Convert stored Elementor query map into a list with keys for blueprints.
     *
     * @param array<string, array> $queries
     * @return array<int, array<string, mixed>>
     */
    private static function convertElementorQueriesToList(array $queries): array
    {
        $result = [];
        foreach ($queries as $key => $definition) {
            if (!is_string($key) || $key === '' || !is_array($definition)) {
                continue;
            }
            $item = self::deepConvert($definition);
            $item['key'] = $key;
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Convert stored schema mappings into a list with keys for blueprints.
     *
     * @param array<string, array> $mappings
     * @return array<int, array<string, mixed>>
     */
    private static function convertSchemaMappingsToList(array $mappings): array
    {
        $result = [];
        foreach ($mappings as $key => $definition) {
            if (!is_string($key) || $key === '' || !is_array($definition)) {
                continue;
            }
            $item = self::deepConvert($definition);
            $item['key'] = $key;
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Resolve Elementor query definitions from blueprint data.
     *
     * @param array<string, mixed> $data
     * @return array<string, array>
     */
    private static function resolveElementorQueriesFromData(array $data): array
    {
        $queries = self::elementorQueryListToMap($data['elementor_query_ids'] ?? []);

        if (!empty($data['elementor']['queries']) && is_array($data['elementor']['queries'])) {
            foreach ($data['elementor']['queries'] as $key => $definition) {
                if (!is_string($key) || $key === '' || !is_array($definition)) {
                    continue;
                }
                if (!isset($queries[$key])) {
                    $queries[$key] = self::deepConvert($definition);
                }
            }
        }

        return $queries;
    }

    /**
     * Extract Elementor templates from blueprint data.
     *
     * @param array<string, mixed> $data
     * @return array<string, array>
     */
    private static function extractElementorTemplatesFromData(array $data): array
    {
        if (!empty($data['elementor']['templates']) && is_array($data['elementor']['templates'])) {
            return self::deepConvert($data['elementor']['templates']);
        }
        return [];
    }

    /**
     * Convert an Elementor query list into a keyed map.
     *
     * @param mixed $value
     * @return array<string, array>
     */
    private static function elementorQueryListToMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? ($entry['id'] ?? null);
            if (!is_string($key) || $key === '') {
                continue;
            }
            $definition = self::deepConvert($entry);
            unset($definition['key']);
            $result[$key] = $definition;
        }

        return $result;
    }

    /**
     * Convert SEO mapping list entries into a keyed map.
     *
     * @param mixed $value
     * @return array<string, array>
     */
    private static function seoMappingListToMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = $entry['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }
            $definition = self::deepConvert($entry);
            unset($definition['key']);
            $result[$key] = $definition;
        }

        return $result;
    }

    /**
     * Normalize Elementor metadata to an array structure.
     */
    private static function normalizeElementor(array $elementor): array
    {
        $queries = [];
        if (!empty($elementor['queries']) && is_array($elementor['queries'])) {
            foreach ($elementor['queries'] as $key => $query) {
                if (is_string($key) && is_array($query)) {
                    $queries[$key] = self::deepConvert($query);
                }
            }
        }

        $templates = [];
        if (!empty($elementor['templates']) && is_array($elementor['templates'])) {
            foreach ($elementor['templates'] as $key => $template) {
                if (is_string($key) && is_array($template)) {
                    $templates[$key] = self::deepConvert($template);
                }
            }
        }

        return [
            'queries'   => $queries,
            'templates' => $templates,
        ];
    }

    /**
     * Retrieve an option value as an array.
     */
    private static function getOptionArray(string $option): array
    {
        $value = get_option($option, []);
        return is_array($value) ? self::deepConvert($value) : [];
    }

    /**
     * Recursively convert objects to arrays.
     */
    private static function deepConvert(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = self::deepConvert($item);
            }
            return $result;
        }
        return $value;
    }

    /**
     * Determine whether the provided array is associative.
     */
    private static function isAssoc(array $array): bool
    {
        return array_keys($array) !== array_keys(array_values($array));
    }

    /**
     * Prepare data for encoding, converting associative arrays to objects.
     */
    private static function prepareForEncoding(mixed $value, array $path = [])
    {
        if (!is_array($value)) {
            return $value;
        }

        $isAssoc = self::isAssoc($value);
        $result = [];
        foreach ($value as $key => $item) {
            $nextPath = $path;
            if (is_string($key)) {
                $nextPath[] = $key;
            } else {
                $nextPath[] = '*';
            }
            $result[$key] = self::prepareForEncoding($item, $nextPath);
        }

        if (self::shouldRepresentAsObject($path, $isAssoc)) {
            return (object) $result;
        }

        if ($isAssoc) {
            return (object) $result;
        }

        return array_values($result);
    }

    /**
     * Determine whether the current path should be encoded as an object.
     */
    private static function shouldRepresentAsObject(array $path, bool $isAssoc): bool
    {
        foreach (self::FORCE_OBJECT_PATHS as $objectPath) {
            if (self::pathMatches($path, $objectPath)) {
                return true;
            }
        }
        return $isAssoc;
    }

    /**
     * Check whether the current path matches a forced-object rule.
     */
    private static function pathMatches(array $path, array $rule): bool
    {
        if (count($path) === 0 && count($rule) === 0) {
            return true;
        }
        if (count($path) < count($rule)) {
            return false;
        }
        $pathSlice = array_slice($path, 0, count($rule));
        foreach ($rule as $index => $segment) {
            if ($segment === '*') {
                continue;
            }
            if (!isset($pathSlice[$index]) || $pathSlice[$index] !== $segment) {
                return false;
            }
        }
        return true;
    }

    /**
     * Normalize an array of mixed values to a string array.
     */
    private static function sanitizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        }
        return array_values(array_unique($result));
    }
}
