<?php

namespace Gm2\GraphQL;

use ArgumentCountError;
use Gm2\Gm2_Capability_Manager;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use WP_Post_Type;
use WP_Taxonomy;

final class Registry
{
    private static ?self $instance = null;

    /**
     * Cached transformers for dynamically registered object types.
     *
     * @var array<string, array<string, array{source:string, transform:callable}>>
     */
    private array $objectTransforms = [];

    public static function init(): void
    {
        if (self::$instance instanceof self) {
            return;
        }

        if (!class_exists('WPGraphQL')) {
            return;
        }

        self::$instance = new self();
        add_action('graphql_register_types', [ self::$instance, 'register' ]);
    }

    public static function defaultSingleName(string $slug, string $label = ''): string
    {
        $source = $label !== '' ? $label : $slug;

        return self::toPascalCase($source);
    }

    public static function defaultPluralName(string $slug, string $label = ''): string
    {
        $source = $label !== '' ? $label : $slug;

        return self::toPascalCase($source);
    }

    public static function defaultFieldName(string $key): string
    {
        return self::toCamelCase($key);
    }

    public static function defaultObjectTypeName(string $parentType, string $fieldKey): string
    {
        return self::toPascalCase($parentType . '_' . $fieldKey);
    }

    public function register(): void
    {
        if (!function_exists('register_graphql_field') || !function_exists('register_graphql_object_type')) {
            return;
        }

        $postTypes = function_exists('get_post_types') ? get_post_types([], 'names') : [];
        if (is_array($postTypes)) {
            foreach ($postTypes as $postType) {
                if (!is_string($postType)) {
                    continue;
                }

                $this->registerPostType($postType);
            }
        }

        $taxonomies = function_exists('get_taxonomies') ? get_taxonomies([], 'names') : [];
        if (is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (!is_string($taxonomy)) {
                    continue;
                }

                $this->registerTaxonomy($taxonomy);
            }
        }
    }

    /**
     * @param string $slug
     */
    private function registerPostType(string $slug): void
    {
        if (!post_type_exists($slug)) {
            return;
        }

        $object = get_post_type_object($slug);
        if (!$object instanceof WP_Post_Type) {
            return;
        }

        if ($object->publicly_queryable === false) {
            return;
        }

        if (function_exists('is_post_type_viewable') && !is_post_type_viewable($object)) {
            return;
        }

        if (isset($object->show_in_graphql) && !$object->show_in_graphql) {
            return;
        }

        $graphqlType = $object->graphql_single_name
            ?? apply_filters(
                'gm2/graphql/post_type_single_name',
                self::defaultSingleName($slug, $object->labels->singular_name ?? ''),
                $slug,
                $object
            );

        if (!is_string($graphqlType) || $graphqlType === '') {
            return;
        }

        $metaKeys = $this->getRegisteredMeta('post', $slug);
        foreach ($metaKeys as $metaKey => $metaArgs) {
            if (!$this->shouldRegisterField($metaKey, $metaArgs, 'post', $slug)) {
                continue;
            }

            $this->registerField($graphqlType, $metaKey, $metaArgs, 'post', $slug);
        }
    }

    /**
     * @param string $slug
     */
    private function registerTaxonomy(string $slug): void
    {
        if (!taxonomy_exists($slug)) {
            return;
        }

        $taxonomy = get_taxonomy($slug);
        if (!$taxonomy instanceof WP_Taxonomy) {
            return;
        }

        if (function_exists('is_taxonomy_viewable') && !is_taxonomy_viewable($taxonomy)) {
            return;
        }

        if (isset($taxonomy->show_in_graphql) && !$taxonomy->show_in_graphql) {
            return;
        }

        $graphqlType = $taxonomy->graphql_single_name
            ?? apply_filters(
                'gm2/graphql/taxonomy_single_name',
                self::defaultSingleName($slug, $taxonomy->labels->singular_name ?? ''),
                $slug,
                $taxonomy
            );

        if (!is_string($graphqlType) || $graphqlType === '') {
            return;
        }

        $metaKeys = $this->getRegisteredMeta('term', $slug);
        foreach ($metaKeys as $metaKey => $metaArgs) {
            if (!$this->shouldRegisterField($metaKey, $metaArgs, 'term', $slug)) {
                continue;
            }

            $this->registerField($graphqlType, $metaKey, $metaArgs, 'term', $slug);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getRegisteredMeta(string $objectType, string $slug): array
    {
        $registered = get_registered_meta_keys($objectType, $slug);

        return is_array($registered) ? $registered : [];
    }

    /**
     * @param array<string, mixed> $metaArgs
     */
    private function shouldRegisterField(string $metaKey, array $metaArgs, string $objectType, string $slug): bool
    {
        if (empty($metaArgs['show_in_rest'])) {
            return false;
        }

        $allowed = apply_filters('gm2/graphql/register_field', true, $metaKey, $metaArgs, $objectType, $slug);

        return (bool) $allowed;
    }

    /**
     * @param array<string, mixed> $metaArgs
     */
    private function registerField(string $graphqlType, string $metaKey, array $metaArgs, string $objectType, string $objectName): void
    {
        $schema = $this->normaliseSchema($metaArgs);
        $defaultName = self::defaultFieldName($metaKey);
        $fieldName = apply_filters(
            'gm2/graphql/field_name',
            $defaultName,
            $metaKey,
            $objectType,
            $objectName,
            $graphqlType
        );

        $fieldName = $this->sanitiseFieldName(is_string($fieldName) ? $fieldName : $defaultName);
        if ($fieldName === '') {
            return;
        }

        $mapping = $this->mapSchemaToGraphQL($schema, $metaKey, $graphqlType);

        $description = null;
        if (!empty($metaArgs['description']) && is_string($metaArgs['description'])) {
            $description = $metaArgs['description'];
        } elseif (!empty($schema['description']) && is_string($schema['description'])) {
            $description = $schema['description'];
        }

        $default = $metaArgs['default'] ?? ($schema['default'] ?? null);
        $single = array_key_exists('single', $metaArgs) ? (bool) $metaArgs['single'] : true;

        $authCallback = null;
        if (isset($metaArgs['auth_callback']) && is_callable($metaArgs['auth_callback'])) {
            $authCallback = $metaArgs['auth_callback'];
        }

        $authCap = $this->resolveAuthCapability($objectType, $objectName);

        $resolver = function ($root, array $args, $context, $info) use ($metaKey, $objectType, $mapping, $single, $default, $authCallback, $authCap) {
            $objectId = $this->resolveObjectId($root, $objectType);
            if (!$objectId) {
                return null;
            }

            if (!Gm2_Capability_Manager::can_read_field($metaKey, $objectId)) {
                return null;
            }

            if ($authCallback) {
                if (!$this->invokeMetaAuthCallback($authCallback, $metaKey, $objectId, $authCap)) {
                    return null;
                }
            }

            $raw = $this->fetchMetaValue($objectType, $objectId, $metaKey, $single);
            if ($this->isEmptyValue($raw) && !$this->isEmptyValue($default)) {
                $raw = $default;
            }

            return $mapping['transform']($raw);
        };

        $config = [
            'type'        => $mapping['type'],
            'description' => $description,
            'resolve'     => $resolver,
        ];

        $config = apply_filters(
            'gm2/graphql/field_config',
            $config,
            $fieldName,
            $metaKey,
            $graphqlType,
            $metaArgs,
            $schema,
            $objectType,
            $objectName
        );

        register_graphql_field($graphqlType, $fieldName, $config);
    }

    private function invokeMetaAuthCallback(callable $authCallback, string $metaKey, int $objectId, string $authCap): bool
    {
        $args = [
            true,
            $metaKey,
            $objectId,
            function_exists('get_current_user_id') ? get_current_user_id() : 0,
            $authCap,
            [],
        ];

        $reflection = $this->reflectCallable($authCallback);

        if ($reflection instanceof ReflectionFunctionAbstract && !$reflection->isVariadic()) {
            $args = array_slice($args, 0, $reflection->getNumberOfParameters());
        }

        try {
            return (bool) call_user_func_array($authCallback, $args);
        } catch (ArgumentCountError $exception) {
            return (bool) call_user_func($authCallback);
        }
    }

    private function reflectCallable(callable $callable): ?ReflectionFunctionAbstract
    {
        try {
            if ($callable instanceof \Closure) {
                return new ReflectionFunction($callable);
            }

            if (is_string($callable)) {
                if (strpos($callable, '::') !== false) {
                    [$class, $method] = explode('::', $callable, 2);

                    return new ReflectionMethod($class, $method);
                }

                return new ReflectionFunction($callable);
            }

            if (is_array($callable) && count($callable) === 2) {
                return new ReflectionMethod($callable[0], (string) $callable[1]);
            }

            if (is_object($callable) && method_exists($callable, '__invoke')) {
                return new ReflectionMethod($callable, '__invoke');
            }
        } catch (ReflectionException $exception) {
            return null;
        }

        return null;
    }

    /**
     * Resolve the capability string passed to meta auth callbacks.
     */
    private function resolveAuthCapability(string $objectType, string $objectName): string
    {
        if ($objectType === 'term') {
            if (taxonomy_exists($objectName)) {
                $taxonomy = get_taxonomy($objectName);
                if ($taxonomy instanceof WP_Taxonomy && isset($taxonomy->cap) && is_object($taxonomy->cap)) {
                    if (!empty($taxonomy->cap->read)) {
                        return (string) $taxonomy->cap->read;
                    }
                    if (!empty($taxonomy->cap->manage_terms)) {
                        return (string) $taxonomy->cap->manage_terms;
                    }
                }
            }

            return 'manage_terms';
        }

        if (post_type_exists($objectName)) {
            $postType = get_post_type_object($objectName);
            if ($postType instanceof WP_Post_Type && isset($postType->cap) && is_object($postType->cap)) {
                if (!empty($postType->cap->read_post)) {
                    return (string) $postType->cap->read_post;
                }
                if (!empty($postType->cap->read)) {
                    return (string) $postType->cap->read;
                }
            }
        }

        return 'read_' . $objectType;
    }

    /**
     * @param array<string, mixed> $metaArgs
     * @return array<string, mixed>
     */
    private function normaliseSchema(array $metaArgs): array
    {
        $schema = $metaArgs['show_in_rest']['schema'] ?? null;
        if (is_array($schema)) {
            return $schema;
        }

        $normalised = [];
        if (!empty($metaArgs['type']) && is_string($metaArgs['type'])) {
            $normalised['type'] = $metaArgs['type'];
        }
        if (!empty($metaArgs['description']) && is_string($metaArgs['description'])) {
            $normalised['description'] = $metaArgs['description'];
        }
        if (array_key_exists('default', $metaArgs)) {
            $normalised['default'] = $metaArgs['default'];
        }

        return $normalised;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array{type:mixed, transform:callable}
     */
    private function mapSchemaToGraphQL(array $schema, string $fieldKey, string $parentType): array
    {
        $type = $this->primaryType($schema['type'] ?? null);

        switch ($type) {
            case 'boolean':
                return [
                    'type'      => 'Boolean',
                    'transform' => static function ($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }
                        if (is_bool($value)) {
                            return $value;
                        }
                        if (is_array($value)) {
                            return !empty($value);
                        }

                        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                        return $filtered ?? null;
                    },
                ];
            case 'integer':
                return [
                    'type'      => 'Int',
                    'transform' => static function ($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }
                        if (is_array($value)) {
                            $value = reset($value);
                        }
                        if (!is_numeric($value)) {
                            return null;
                        }

                        return (int) $value;
                    },
                ];
            case 'number':
                return [
                    'type'      => 'Float',
                    'transform' => static function ($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }
                        if (is_array($value)) {
                            $value = reset($value);
                        }
                        if (!is_numeric($value)) {
                            return null;
                        }

                        return (float) $value;
                    },
                ];
            case 'array':
                $items = is_array($schema['items'] ?? null) ? $schema['items'] : null;
                if ($items === null) {
                    return [
                        'type'      => 'JSON',
                        'transform' => function ($value) {
                            $list = $this->coerceArray($value);

                            return $list ?? null;
                        },
                    ];
                }

                $itemMapping = $this->mapSchemaToGraphQL($items, $fieldKey . '_item', $parentType);

                return [
                    'type'      => [ 'list_of' => $itemMapping['type'] ],
                    'transform' => function ($value) use ($itemMapping) {
                        $list = $this->coerceArray($value);
                        if ($list === null) {
                            return null;
                        }

                        $normalized = [];
                        foreach ($list as $entry) {
                            $normalized[] = $itemMapping['transform']($entry);
                        }

                        return $normalized;
                    },
                ];
            case 'object':
                $properties = $schema['properties'] ?? [];
                if (is_array($properties) && $properties !== []) {
                    return $this->registerObjectType($schema, $fieldKey, $parentType);
                }

                return [
                    'type'      => 'JSON',
                    'transform' => function ($value) {
                        $object = $this->coerceObject($value);

                        return $object ?? null;
                    },
                ];
            case 'string':
            default:
                return [
                    'type'      => 'String',
                    'transform' => static function ($value) {
                        if ($value === null) {
                            return null;
                        }
                        if (is_array($value)) {
                            if ($value === []) {
                                return null;
                            }

                            return wp_json_encode($value);
                        }
                        if ($value === '') {
                            return null;
                        }

                        return (string) $value;
                    },
                ];
        }
    }

    /**
     * @param array<string, mixed> $schema
     * @return array{type:mixed, transform:callable}
     */
    private function registerObjectType(array $schema, string $fieldKey, string $parentType): array
    {
        $typeName = apply_filters(
            'gm2/graphql/object_type_name',
            self::defaultObjectTypeName($parentType, $fieldKey),
            $fieldKey,
            $parentType,
            $schema
        );

        $typeName = $this->sanitiseTypeName(is_string($typeName) ? $typeName : self::defaultObjectTypeName($parentType, $fieldKey));
        if ($typeName === '') {
            $typeName = self::defaultObjectTypeName($parentType, $fieldKey);
        }

        if (!isset($this->objectTransforms[$typeName])) {
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            $fields     = [];
            $transforms = [];

            foreach ($properties as $propKey => $propSchema) {
                if (!is_array($propSchema)) {
                    continue;
                }

                $defaultName = self::defaultFieldName((string) $propKey);
                $propFieldName = apply_filters(
                    'gm2/graphql/field_name',
                    $defaultName,
                    (string) $propKey,
                    'object',
                    $fieldKey,
                    $typeName
                );
                $propFieldName = $this->sanitiseFieldName(is_string($propFieldName) ? $propFieldName : $defaultName);
                if ($propFieldName === '') {
                    continue;
                }

                $mapping = $this->mapSchemaToGraphQL($propSchema, $fieldKey . '_' . $propKey, $typeName);

                $fieldConfig = [ 'type' => $mapping['type'] ];
                if (!empty($propSchema['description']) && is_string($propSchema['description'])) {
                    $fieldConfig['description'] = $propSchema['description'];
                }

                $fields[$propFieldName]     = $fieldConfig;
                $transforms[$propFieldName] = [
                    'source'    => (string) $propKey,
                    'transform' => $mapping['transform'],
                ];
            }

            register_graphql_object_type($typeName, [ 'fields' => $fields ]);
            $this->objectTransforms[$typeName] = $transforms;
        }

        return [
            'type'      => $typeName,
            'transform' => function ($value) use ($typeName) {
                $object = $this->coerceObject($value);
                if ($object === null) {
                    return null;
                }

                $result = [];
                foreach ($this->objectTransforms[$typeName] as $fieldName => $info) {
                    $source    = $info['source'];
                    $transform = $info['transform'];
                    $result[$fieldName] = $transform($object[$source] ?? null);
                }

                return $result;
            },
        ];
    }

    private function fetchMetaValue(string $objectType, int $objectId, string $metaKey, bool $single)
    {
        if ($objectType === 'term') {
            $value = $single ? get_term_meta($objectId, $metaKey, true) : get_term_meta($objectId, $metaKey, false);
        } else {
            $value = $single ? get_post_meta($objectId, $metaKey, true) : get_post_meta($objectId, $metaKey, false);
        }

        return $value;
    }

    private function resolveObjectId($root, string $objectType): ?int
    {
        if (is_object($root)) {
            if (isset($root->ID)) {
                return (int) $root->ID;
            }
            if (isset($root->term_id)) {
                return (int) $root->term_id;
            }
            if (isset($root->databaseId)) {
                return (int) $root->databaseId;
            }
            if (method_exists($root, 'ID')) {
                return (int) $root->ID();
            }
            if (method_exists($root, 'databaseId')) {
                return (int) $root->databaseId();
            }
        }

        if (is_array($root)) {
            if (isset($root['ID'])) {
                return (int) $root['ID'];
            }
            if (isset($root['term_id'])) {
                return (int) $root['term_id'];
            }
            if (isset($root['databaseId'])) {
                return (int) $root['databaseId'];
            }
        }

        if ($objectType === 'term' && function_exists('absint')) {
            return isset($root) ? absint($root) : null;
        }

        return null;
    }

    private function isEmptyValue($value): bool
    {
        if ($value === null) {
            return true;
        }
        if ($value === '') {
            return true;
        }
        if (is_array($value) || is_object($value)) {
            return empty((array) $value);
        }

        return false;
    }

    private function coerceArray($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return array_values($value);
        }
        if ($value instanceof \Traversable) {
            return iterator_to_array($value, false);
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values($decoded);
            }
        }

        return [ $value ];
    }

    private function coerceObject($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param mixed $type
     */
    private function primaryType($type): ?string
    {
        if (is_string($type) && $type !== '') {
            return $type;
        }
        if (is_array($type)) {
            foreach ($type as $candidate) {
                if ($candidate === 'null') {
                    continue;
                }
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function sanitiseFieldName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
        if ($name === '') {
            return '';
        }
        if (preg_match('/^[0-9]/', $name)) {
            $name = '_' . $name;
        }

        return $name;
    }

    private function sanitiseTypeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
        if ($name === '') {
            return '';
        }
        if (preg_match('/^[0-9]/', $name)) {
            $name = 'T' . $name;
        }

        return $name;
    }

    private static function toPascalCase(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', $value) ?? '';
        $parts = array_filter(explode(' ', trim($value)));
        $parts = array_map(static fn ($part) => ucfirst(strtolower($part)), $parts);

        return implode('', $parts);
    }

    private static function toCamelCase(string $value): string
    {
        $pascal = self::toPascalCase($value);
        if ($pascal === '') {
            return '';
        }

        return lcfirst($pascal);
    }
}
