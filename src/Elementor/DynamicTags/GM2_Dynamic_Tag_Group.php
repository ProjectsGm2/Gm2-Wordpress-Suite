<?php

declare(strict_types=1);

namespace Gm2\Elementor\DynamicTags;

use DateTimeImmutable;
use Exception;
use Gm2\Elementor\DynamicTags\Tag\AddressMapLink;
use Gm2\Elementor\DynamicTags\Tag\ComputedValue;
use Gm2\Elementor\DynamicTags\Tag\FieldValue;
use Gm2\Fields\FieldDefinition;
use Gm2\Fields\FieldGroupDefinition;
use Gm2\Fields\FieldTypeRegistry;
use Gm2\Fields\Sanitizers\SanitizerRegistry;
use Gm2\Fields\Types\FieldTypeInterface;
use InvalidArgumentException;
use WP_Post;
use WP_Term;
use WP_User;
use function esc_url_raw;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_post_type;
use function get_post_type_object;
use function get_term;
use function get_term_meta;
use function get_user_by;
use function get_user_meta;
use function get_queried_object;
use function get_queried_object_id;
use function is_array;
use function is_numeric;
use function is_string;
use function number_format_i18n;
use function rawurlencode;
use function sanitize_hex_color;
use function sanitize_key;
use function sanitize_text_field;
use function trim;
use function wp_date;
use function wp_get_attachment_url;
use function wp_strip_all_tags;
use Elementor\Modules\DynamicTags\Module;

final class GM2_Dynamic_Tag_Group
{
    private const GROUP = 'gm2';

    private static ?self $instance = null;

    private FieldTypeRegistry $fieldTypes;

    private SanitizerRegistry $sanitizers;

    /**
     * @var array<string, FieldGroupDefinition>
     */
    private array $groups = [];

    /**
     * @var array<string, array{group: FieldGroupDefinition, field: FieldDefinition}>
     */
    private array $fieldIndex = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $postTypeFields = [];

    /**
     * @var array<string, array<string, array<int, string>>>
     */
    private array $computedGraphs = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $valueCache = [];

    private string $snapshot = '';

    private function __construct(?FieldTypeRegistry $fieldTypes = null, ?SanitizerRegistry $sanitizers = null)
    {
        $this->fieldTypes = $fieldTypes ?? FieldTypeRegistry::withDefaults();
        $this->sanitizers = $sanitizers ?? SanitizerRegistry::withDefaults();
        $this->loadGroups();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function refresh(): void
    {
        $this->snapshot   = '';
        $this->groups     = [];
        $this->fieldIndex = [];
        $this->postTypeFields = [];
        $this->computedGraphs = [];
        $this->valueCache = [];
        $this->loadGroups(true);
    }

    public function register(Module $module): void
    {
        $module->register_group(self::GROUP, [
            'title' => __('GM2 Fields', 'gm2-wordpress-suite'),
        ]);

        FieldValue::setGroup($this);
        ComputedValue::setGroup($this);
        AddressMapLink::setGroup($this);

        $module->register_tag(FieldValue::class);
        $module->register_tag(ComputedValue::class);
        $module->register_tag(AddressMapLink::class);
    }

    public function getGroupName(): string
    {
        return self::GROUP;
    }

    /**
     * @return array<string, string>
     */
    public function getPostTypeOptions(): array
    {
        $this->loadGroups();

        $options = ['' => __('Current Post', 'gm2-wordpress-suite')];
        foreach ($this->postTypeFields as $postType => $_fields) {
            $object = get_post_type_object($postType);
            $label  = $object ? $object->labels->singular_name : ucfirst(str_replace('_', ' ', $postType));
            $options[$postType] = $label;
        }

        return $options;
    }

    /**
     * @param array<int, string> $allowedTypes
     *
     * @return array<string, string>
     */
    public function getFieldOptions(?string $postType = null, bool $includeComputed = true, array $allowedTypes = []): array
    {
        $this->loadGroups();

        $options = [];
        $targets = $postType ? [$postType] : array_keys($this->postTypeFields);

        foreach ($targets as $type) {
            if (!isset($this->postTypeFields[$type])) {
                continue;
            }
            foreach ($this->postTypeFields[$type] as $compoundKey => $label) {
                $field = $this->fieldIndex[$compoundKey]['field'] ?? null;
                if (!$field) {
                    continue;
                }
                if (!$includeComputed && $field->isComputed()) {
                    continue;
                }
                if ($allowedTypes !== [] && !in_array($field->getType()->getName(), $allowedTypes, true)) {
                    continue;
                }
                $options[$compoundKey] = $label . ' (' . $type . ')';
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function getComputedFieldOptions(?string $postType = null): array
    {
        return $this->getFieldOptions($postType, true, ['computed']);
    }

    /**
     * @return array<string, string>
     */
    public function getMapFieldOptions(?string $postType = null): array
    {
        return $this->getFieldOptions($postType, false, ['geopoint', 'address']);
    }

    /**
     * @return array{group: FieldGroupDefinition, field: FieldDefinition}|null
     */
    public function findField(string $compoundKey): ?array
    {
        $this->loadGroups();

        return $this->fieldIndex[$compoundKey] ?? null;
    }

    public function getCategories(FieldDefinition $field): array
    {
        return $this->categoriesForType($field->getType());
    }

    public function getComputedCategories(FieldDefinition $field): array
    {
        $settings   = $field->getSettings()['computed'] ?? [];
        $returnType = is_array($settings) ? ($settings['return_type'] ?? null) : null;
        if (!is_string($returnType)) {
            $returnType = 'string';
        }

        return match ($returnType) {
            'number', 'integer' => [Module::NUMBER_CATEGORY],
            'date', 'datetime', 'datetime_tz' => [Module::DATETIME_CATEGORY],
            default => [Module::TEXT_CATEGORY],
        };
    }

    public function getFormattedValue(string $compoundKey, ?string $postType = null, ?array $context = null): mixed
    {
        $fieldData = $this->findField($compoundKey);
        if ($fieldData === null) {
            return null;
        }

        [$group, $field] = [$fieldData['group'], $fieldData['field']];
        $context = $context ?? $this->resolveContext($postType, $group);
        if (($context['id'] ?? 0) <= 0) {
            return null;
        }

        $value = $this->getSanitizedFieldValue($group, $field, $context);

        return $this->formatValue($field, $value, $context);
    }

    public function getSanitizedValue(string $compoundKey, ?string $postType = null, ?array $context = null): mixed
    {
        $fieldData = $this->findField($compoundKey);
        if ($fieldData === null) {
            return null;
        }

        [$group, $field] = [$fieldData['group'], $fieldData['field']];
        $context = $context ?? $this->resolveContext($postType, $group);
        if (($context['id'] ?? 0) <= 0) {
            return null;
        }

        return $this->getSanitizedFieldValue($group, $field, $context);
    }

    public function formatFallback(FieldDefinition $field, mixed $fallback): mixed
    {
        $fallback ??= '';
        $type = $field->getType()->getName();

        return match ($type) {
            'image', 'media', 'file' => [
                'id'  => 0,
                'url' => is_string($fallback) ? esc_url_raw($fallback) : '',
            ],
            'gallery' => [],
            default => is_string($fallback) ? sanitize_text_field($fallback) : (string) $fallback,
        };
    }

    public function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            if ($value === []) {
                return true;
            }
            if (isset($value['url'])) {
                return trim((string) $value['url']) === '';
            }
            if (isset($value['ids'])) {
                return $value['ids'] === [];
            }
        }

        return false;
    }

    /**
     * @return array{type: string, id: int, post_type: string}
     */
    public function resolveContext(?string $postType = null, ?FieldGroupDefinition $group = null): array
    {
        $postType = is_string($postType) ? $postType : '';

        if ($group && $postType === '') {
            $postTypes = $this->getPostTypesForGroup($group);
            if ($postTypes !== []) {
                $postType = $postTypes[0];
            }
        }

        $id = 0;
        $object = get_post();
        if ($object instanceof WP_Post) {
            if ($postType === '' || $object->post_type === $postType) {
                $id = (int) $object->ID;
                $postType = $object->post_type;
            }
        }

        if ($id === 0) {
            $loopId = get_the_ID();
            if ($loopId) {
                $type = get_post_type($loopId) ?: '';
                if ($postType === '' || $postType === $type) {
                    $id = (int) $loopId;
                    $postType = $type ?: $postType;
                }
            }
        }

        if ($id === 0) {
            $queriedId = get_queried_object_id();
            if ($queriedId) {
                $id = (int) $queriedId;
                $object = get_queried_object();
                if ($object instanceof WP_Post) {
                    $postType = $object->post_type;
                }
            }
        }

        return [
            'type'      => 'post',
            'id'        => $id,
            'post_type' => $postType,
        ];
    }

    private function loadGroups(bool $force = false): void
    {
        $raw = get_option('gm2_field_groups', []);
        $snapshot = is_array($raw) ? md5(serialize($raw)) : '';
        if (!$force && $snapshot === $this->snapshot) {
            return;
        }

        $this->snapshot        = $snapshot;
        $this->groups          = [];
        $this->fieldIndex      = [];
        $this->postTypeFields  = [];
        $this->computedGraphs  = [];
        $this->valueCache      = [];

        if (!is_array($raw) || $raw === []) {
            return;
        }

        foreach ($raw as $key => $config) {
            if (!is_array($config)) {
                continue;
            }
            $groupKey = $this->determineGroupKey($key, $config);
            if ($groupKey === '') {
                continue;
            }

            $group = $this->createGroupDefinition($groupKey, $config);
            if (!$group) {
                continue;
            }

            $this->groups[$groupKey] = $group;
            $this->computedGraphs[$groupKey] = $group->getComputedDependencyGraph();

            foreach ($group->getFields() as $field) {
                $compound = $groupKey . '::' . $field->getKey();
                $this->fieldIndex[$compound] = [
                    'group' => $group,
                    'field' => $field,
                ];
            }

            $postTypes = $this->getPostTypesForGroup($group, $config);
            foreach ($postTypes as $postType) {
                foreach ($group->getFields() as $field) {
                    $compound = $groupKey . '::' . $field->getKey();
                    $label    = $group->getTitle() . ' – ' . $field->getLabel();
                    $this->postTypeFields[$postType][$compound] = $label;
                }
            }
        }
    }

    private function determineGroupKey(string|int $key, array $config): string
    {
        if (is_string($key) && $key !== '') {
            return sanitize_key($key);
        }

        $possible = $config['key'] ?? $config['slug'] ?? $config['id'] ?? null;
        if (is_string($possible) && $possible !== '') {
            return sanitize_key($possible);
        }

        if (isset($config['title']) && is_string($config['title'])) {
            return sanitize_key($config['title']);
        }

        return '';
    }

    private function createGroupDefinition(string $key, array $config): ?FieldGroupDefinition
    {
        $fieldsConfig = $config['fields'] ?? [];
        if (!is_array($fieldsConfig) || $fieldsConfig === []) {
            return null;
        }

        $definitions = [];
        foreach ($fieldsConfig as $fieldKey => $settings) {
            if (!is_string($fieldKey) || $fieldKey === '' || !is_array($settings)) {
                continue;
            }
            $typeName = is_string($settings['type'] ?? null) ? $settings['type'] : 'text';

            try {
                $type = $this->fieldTypes->create($typeName, $settings);
            } catch (InvalidArgumentException) {
                continue;
            }

            $definitions[$fieldKey] = new FieldDefinition($fieldKey, $type, $settings);
        }

        if ($definitions === []) {
            return null;
        }

        try {
            return new FieldGroupDefinition($key, $config, $definitions);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return string[]
     */
    private function getPostTypesForGroup(FieldGroupDefinition $group, ?array $config = null): array
    {
        $postTypes = $group->getPostTypes();
        if ($postTypes !== []) {
            return $postTypes;
        }

        $config ??= [];
        $raw = $config['post_types'] ?? $config['objects'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $filtered = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $filtered[] = $item;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function getSanitizedFieldValue(FieldGroupDefinition $group, FieldDefinition $field, array $context): mixed
    {
        $contextKey = $this->contextCacheKey($context);
        $compound   = $group->getKey() . '::' . $field->getKey();

        if (isset($this->valueCache[$contextKey][$compound])) {
            return $this->valueCache[$contextKey][$compound];
        }

        if ($field->isComputed()) {
            $value = $this->resolveComputed($group, $field, $context);
        } else {
            $raw = $this->fetchRawValue($field, $context);
            if (($raw === null || $raw === '') && $field->getDefault() !== null) {
                $raw = $field->getDefault();
            }
            $value = $this->sanitizers->sanitize($field, $raw, $context);
        }

        $this->valueCache[$contextKey][$compound] = $value;

        return $value;
    }

    private function fetchRawValue(FieldDefinition $field, array $context): mixed
    {
        $key = $field->getKey();
        switch ($context['type']) {
            case 'term':
                return get_term_meta($context['id'], $key, true);
            case 'user':
                return get_user_meta($context['id'], $key, true);
            default:
                return get_post_meta($context['id'], $key, true);
        }
    }

    private function contextCacheKey(array $context): string
    {
        $type = $context['type'] ?? 'post';
        $id   = (int) ($context['id'] ?? 0);

        return $type . ':' . $id;
    }

    private function formatValue(FieldDefinition $field, mixed $value, array $context): mixed
    {
        $type = $field->getType()->getName();

        return match ($type) {
            'currency' => $this->formatCurrency($value, $field->getSettings()),
            'number' => is_numeric($value) ? number_format_i18n((float) $value) : $value,
            'date' => is_string($value) ? $this->formatDate($value) : $value,
            'time' => is_string($value) ? $this->formatTime($value) : $value,
            'datetime_tz' => is_string($value) ? $this->formatDateTime($value) : $value,
            'image', 'media', 'file' => $this->formatAttachment($value, $field->getSettings()),
            'gallery' => $this->formatGallery($value),
            'relationship_post' => $this->formatRelationshipPosts($value, $field),
            'relationship_term' => $this->formatRelationshipTerms($value, $field),
            'relationship_user' => $this->formatRelationshipUsers($value, $field),
            'color' => is_string($value) ? sanitize_hex_color($value) : $value,
            'computed' => $this->formatComputed($value, $field->getSettings()),
            default => $value,
        };
    }

    private function formatCurrency(mixed $value, array $settings): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }
        $precision = is_numeric($settings['precision'] ?? null) ? (int) $settings['precision'] : 2;
        $formatted = number_format_i18n((float) $value, $precision);
        $symbol = '';
        if (isset($settings['symbol']) && is_string($settings['symbol'])) {
            $symbol = $settings['symbol'];
        } else {
            $symbol = $this->currencySymbol($settings['currency'] ?? null);
        }

        if ($symbol !== '') {
            return $symbol . $formatted;
        }

        $code = is_string($settings['currency'] ?? null) ? $settings['currency'] : '';
        if ($code !== '') {
            return $code . ' ' . $formatted;
        }

        return $formatted;
    }

    private function currencySymbol(mixed $currency): string
    {
        if (!is_string($currency) || $currency === '') {
            return '';
        }

        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => '$',
            'CAD' => '$',
            default => '',
        };
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date(get_option('date_format'), $timestamp);
    }

    private function formatTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date(get_option('time_format'), $timestamp);
    }

    private function formatDateTime(string $value): string
    {
        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return $value;
            }

            return wp_date(get_option('date_format') . ' ' . get_option('time_format') . ' T', $timestamp);
        }

        $timestamp = $date->getTimestamp();

        return wp_date(get_option('date_format') . ' ' . get_option('time_format') . ' T', $timestamp);
    }

    private function formatAttachment(mixed $value, array $settings): array
    {
        $id = 0;
        $url = '';

        if (is_numeric($value) && (int) $value > 0) {
            $id  = (int) $value;
            $url = wp_get_attachment_url($id) ?: '';
        } elseif (is_string($value) && $value !== '') {
            $url = esc_url_raw($value);
        }

        if ($url === '' && isset($settings['default']) && is_string($settings['default'])) {
            $url = esc_url_raw($settings['default']);
        }

        return [
            'id'  => $id,
            'url' => $url,
        ];
    }

    private function formatGallery(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $id) {
            if (!is_numeric($id)) {
                continue;
            }
            $attachmentId = (int) $id;
            $items[] = [
                'id'  => $attachmentId,
                'url' => wp_get_attachment_url($attachmentId) ?: '',
            ];
        }

        return $items;
    }

    private function formatRelationshipPosts(mixed $value, FieldDefinition $field): string
    {
        $ids = $this->normalizeRelationshipIds($value, $field);
        if ($ids === []) {
            return '';
        }

        $titles = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if ($post instanceof WP_Post) {
                $titles[] = wp_strip_all_tags($post->post_title);
            }
        }

        return implode(', ', $titles);
    }

    private function formatRelationshipTerms(mixed $value, FieldDefinition $field): string
    {
        $ids = $this->normalizeRelationshipIds($value, $field);
        if ($ids === []) {
            return '';
        }

        $names = [];
        foreach ($ids as $id) {
            $term = get_term($id);
            if ($term instanceof WP_Term) {
                $names[] = sanitize_text_field($term->name);
            }
        }

        return implode(', ', $names);
    }

    private function formatRelationshipUsers(mixed $value, FieldDefinition $field): string
    {
        $ids = $this->normalizeRelationshipIds($value, $field);
        if ($ids === []) {
            return '';
        }

        $names = [];
        foreach ($ids as $id) {
            $user = get_user_by('id', $id);
            if ($user instanceof WP_User) {
                $names[] = sanitize_text_field($user->display_name);
            }
        }

        return implode(', ', $names);
    }

    private function formatComputed(mixed $value, array $settings): mixed
    {
        $computedSettings = is_array($settings['computed'] ?? null) ? $settings['computed'] : $settings;

        if (isset($computedSettings['format']) && $computedSettings['format'] === 'currency') {
            return $this->formatCurrency(
                $value,
                $computedSettings + ['precision' => $computedSettings['precision'] ?? 2]
            );
        }

        $returnType = is_string($computedSettings['return_type'] ?? null) ? $computedSettings['return_type'] : 'string';
        if (in_array($returnType, ['number', 'integer'], true) && is_numeric($value)) {
            $precision = is_numeric($computedSettings['precision'] ?? null) ? (int) $computedSettings['precision'] : 2;
            return number_format_i18n((float) $value, $precision);
        }

        return $value;
    }

    private function normalizeRelationshipIds(mixed $value, FieldDefinition $field): array
    {
        $settings = $field->getSettings();
        $multiple = (bool) ($settings['multiple'] ?? true);

        if ($multiple) {
            if (!is_array($value)) {
                return [];
            }
            $ids = [];
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $ids[] = (int) $item;
                }
            }

            return array_values(array_unique($ids));
        }

        if (is_numeric($value)) {
            return [ (int) $value ];
        }

        return [];
    }

    private function resolveComputed(FieldGroupDefinition $group, FieldDefinition $field, array $context): mixed
    {
        $graph = $this->computedGraphs[$group->getKey()] ?? [];
        $dependencies = $graph[$field->getKey()] ?? $field->getComputedDependencies();

        $values = [];
        foreach ($dependencies as $dependencyKey) {
            $dependency = $group->getField($dependencyKey);
            $values[$dependencyKey] = $this->getSanitizedFieldValue($group, $dependency, $context);
        }

        $settings = $field->getSettings()['computed'] ?? [];
        $settings = is_array($settings) ? $settings : [];

        if (isset($settings['formula']) && is_string($settings['formula'])) {
            return $this->evaluateFormula($settings['formula'], $values);
        }

        if (isset($settings['callback']) && is_callable($settings['callback'])) {
            return ($settings['callback'])($values, $context);
        }

        return null;
    }

    private function evaluateFormula(string $formula, array $values): mixed
    {
        if (!preg_match_all('/{([^}]+)}/', $formula, $matches)) {
            return null;
        }

        $replacements = [];
        foreach ($matches[1] as $key) {
            if (!array_key_exists($key, $values)) {
                return null;
            }
            $numeric = $this->sanitizeNumericValue($values[$key]);
            if ($numeric === null) {
                return null;
            }
            $replacements['{' . $key . '}'] = $numeric;
        }

        $expression = strtr($formula, $replacements);
        if (preg_match('/[^0-9+\-*\/().\s]/', $expression)) {
            return null;
        }

        return $this->evaluateNumericExpression(trim($expression));
    }

    private function sanitizeNumericValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $filtered = trim($value);
            if ($filtered === '') {
                return '0';
            }
            if (preg_match('/^-?(?:\d+\.?\d*|\d*\.\d+)$/', $filtered)) {
                return $filtered;
            }
            if (is_numeric($filtered)) {
                return (string) (float) $filtered;
            }
        }

        return null;
    }

    private function evaluateNumericExpression(string $expression): mixed
    {
        if ($expression === '') {
            return null;
        }

        $length = strlen($expression);
        $position = 0;

        $value = $this->parseExpressionLevel($expression, $position, $length);
        if ($value === null) {
            return null;
        }

        $this->skipWhitespace($expression, $position, $length);
        if ($position !== $length) {
            return null;
        }

        return $value;
    }

    private function parseExpressionLevel(string $expression, int &$position, int $length): ?float
    {
        $value = $this->parseTerm($expression, $position, $length);
        if ($value === null) {
            return null;
        }

        while (true) {
            $this->skipWhitespace($expression, $position, $length);
            if ($position >= $length) {
                break;
            }

            $operator = $expression[$position];
            if ($operator !== '+' && $operator !== '-') {
                break;
            }
            $position++;

            $right = $this->parseTerm($expression, $position, $length);
            if ($right === null) {
                return null;
            }

            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(string $expression, int &$position, int $length): ?float
    {
        $value = $this->parseFactor($expression, $position, $length);
        if ($value === null) {
            return null;
        }

        while (true) {
            $this->skipWhitespace($expression, $position, $length);
            if ($position >= $length) {
                break;
            }

            $operator = $expression[$position];
            if ($operator !== '*' && $operator !== '/') {
                break;
            }
            $position++;

            $right = $this->parseFactor($expression, $position, $length);
            if ($right === null) {
                return null;
            }

            $value = $operator === '*' ? $value * $right : ($right != 0.0 ? $value / $right : null);
            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    private function parseFactor(string $expression, int &$position, int $length): ?float
    {
        $this->skipWhitespace($expression, $position, $length);
        if ($position >= $length) {
            return null;
        }

        $char = $expression[$position];
        if ($char === '(') {
            $position++;
            $value = $this->parseExpressionLevel($expression, $position, $length);
            if ($value === null) {
                return null;
            }
            $this->skipWhitespace($expression, $position, $length);
            if ($position >= $length || $expression[$position] !== ')') {
                return null;
            }
            $position++;

            return $value;
        }

        $start = $position;
        if ($char === '+' || $char === '-') {
            $position++;
        }

        while ($position < $length && (ctype_digit($expression[$position]) || $expression[$position] === '.')) {
            $position++;
        }

        if ($start === $position) {
            return null;
        }

        $number = substr($expression, $start, $position - $start);

        return is_numeric($number) ? (float) $number : null;
    }

    private function skipWhitespace(string $expression, int &$position, int $length): void
    {
        while ($position < $length && ctype_space($expression[$position])) {
            $position++;
        }
    }

    private function categoriesForType(FieldTypeInterface $type): array
    {
        return match ($type->getName()) {
            'url', 'email', 'tel' => [Module::URL_CATEGORY],
            'image' => [Module::IMAGE_CATEGORY],
            'media', 'file' => [Module::MEDIA_CATEGORY],
            'gallery' => [Module::GALLERY_CATEGORY],
            'number', 'currency' => [Module::NUMBER_CATEGORY],
            'date', 'time', 'datetime_tz' => [Module::DATETIME_CATEGORY],
            'color' => [Module::COLOR_CATEGORY],
            default => [Module::TEXT_CATEGORY],
        };
    }

    public function buildMapUrl(string $compoundKey, ?string $postType = null, ?array $context = null): ?string
    {
        $value = $this->getSanitizedValue($compoundKey, $postType, $context);
        if ($value === null) {
            return null;
        }

        $query = $this->mapQueryFromValue($value);
        if ($query === null || $query === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
    }

    private function mapQueryFromValue(mixed $value): ?string
    {
        if (is_array($value)) {
            if (isset($value['lat'], $value['lng']) && is_numeric($value['lat']) && is_numeric($value['lng'])) {
                return $this->formatCoordinate((float) $value['lat']) . ',' . $this->formatCoordinate((float) $value['lng']);
            }

            $parts = [];
            foreach (['line1', 'line2', 'city', 'state', 'postal_code', 'country'] as $key) {
                if (isset($value[$key]) && is_string($value[$key]) && $value[$key] !== '') {
                    $parts[] = $value[$key];
                }
            }
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private function formatCoordinate(float $value): string
    {
        $formatted = number_format($value, 6, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
