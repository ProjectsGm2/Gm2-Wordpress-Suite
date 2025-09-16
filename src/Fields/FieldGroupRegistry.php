<?php

namespace Gm2\Fields;

use Gm2\Fields\Renderer\AdminMetaBox;
use Gm2\Fields\Sanitizers\SanitizerRegistry;
use Gm2\Fields\Storage\MetaRegistrar;
use Gm2\Fields\Validation\ValidatorRegistry;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;

final class FieldGroupRegistry
{
    /**
     * @var array<string, FieldGroupDefinition>
     */
    private array $groups = [];

    public function __construct(
        private readonly MetaRegistrar $metaRegistrar,
        private readonly FieldTypeRegistry $fieldTypes,
        private readonly ValidatorRegistry $validatorRegistry,
        private readonly SanitizerRegistry $sanitizerRegistry,
        private readonly ?AdminMetaBox $adminRenderer = null
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function registerGroup(string $key, array $config): FieldGroupDefinition
    {
        if ($key === '') {
            throw new InvalidArgumentException('Field group key cannot be empty.');
        }

        $fieldsConfig = $config['fields'] ?? [];
        if (!is_array($fieldsConfig) || $fieldsConfig === []) {
            throw new InvalidArgumentException(sprintf('Field group "%s" must define fields.', $key));
        }

        $definitions = [];
        foreach ($fieldsConfig as $fieldKey => $settings) {
            if (!is_string($fieldKey) || $fieldKey === '') {
                continue;
            }
            if (!is_array($settings)) {
                continue;
            }
            $typeName = $settings['type'] ?? 'text';
            if (!is_string($typeName)) {
                $typeName = 'text';
            }
            $fieldType = $this->fieldTypes->create($typeName, $settings);
            $definitions[$fieldKey] = new FieldDefinition($fieldKey, $fieldType, $settings);
        }

        if ($definitions === []) {
            throw new InvalidArgumentException(sprintf('Field group "%s" does not contain valid fields.', $key));
        }

        $group = new FieldGroupDefinition($key, $config, $definitions);
        $this->groups[$key] = $group;

        return $group;
    }

    public function hasGroup(string $key): bool
    {
        return isset($this->groups[$key]);
    }

    public function getGroup(string $key): FieldGroupDefinition
    {
        if (!isset($this->groups[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown field group "%s".', $key));
        }

        return $this->groups[$key];
    }

    /**
     * @return array<string, FieldGroupDefinition>
     */
    public function all(): array
    {
        return $this->groups;
    }

    public function boot(): void
    {
        foreach ($this->groups as $group) {
            foreach ($group->getPostTypes() as $postType) {
                foreach ($group->getFields() as $field) {
                    $this->metaRegistrar->registerPostField(
                        $postType,
                        $field,
                        $this->createSanitizeCallback($field),
                        $this->createValidateCallback($group, $field)
                    );
                }
            }

            foreach ($group->getTaxonomies() as $taxonomy) {
                foreach ($group->getFields() as $field) {
                    $this->metaRegistrar->registerTermField(
                        $taxonomy,
                        $field,
                        $this->createSanitizeCallback($field),
                        $this->createValidateCallback($group, $field)
                    );
                }
            }

            if ($group->appliesToUsers()) {
                foreach ($group->getFields() as $field) {
                    $this->metaRegistrar->registerUserField(
                        $field,
                        $this->createSanitizeCallback($field),
                        $this->createValidateCallback($group, $field)
                    );
                }
            }

            if ($this->adminRenderer) {
                $this->adminRenderer->addGroup($group);
            }
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeGroup(string $groupKey, array $values): array
    {
        $group     = $this->getGroup($groupKey);
        $sanitized = [];
        foreach ($group->getFields() as $field) {
            $key          = $field->getKey();
            $value        = $values[$key] ?? $field->getDefault();
            $sanitized[$key] = $this->sanitizerRegistry->sanitize($field, $value, $values);
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, WP_Error>
     */
    public function validateGroup(string $groupKey, array $values): array
    {
        $group  = $this->getGroup($groupKey);
        $errors = [];

        foreach ($group->getFields() as $field) {
            if ($field->isComputed()) {
                continue;
            }

            if (!$this->shouldEvaluateField($group, $field, $values)) {
                continue;
            }

            $value = $values[$field->getKey()] ?? null;
            if ($field->isRequired() && $this->isEmpty($value)) {
                $errors[$field->getKey()] = new WP_Error(
                    'gm2_field_required',
                    sprintf('%s is required.', $field->getLabel()),
                    [ 'field' => $field->getKey() ]
                );
                continue;
            }

            $result = $this->validatorRegistry->validate($field, $value, $values);
            if ($result instanceof WP_Error) {
                $errors[$field->getKey()] = $result;
            }
        }

        return $errors;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getComputedDependencyGraph(string $groupKey): array
    {
        return $this->getGroup($groupKey)->getComputedDependencyGraph();
    }

    private function createSanitizeCallback(FieldDefinition $field): callable
    {
        return function ($value, $metaKey = '', $objectType = '') use ($field) {
            $default = $field->getDefault();
            $value   = $value ?? $default;

            return $this->sanitizerRegistry->sanitize($field, $value, [ $field->getKey() => $value ]);
        };
    }

    private function createValidateCallback(FieldGroupDefinition $group, FieldDefinition $field): callable
    {
        return function ($value, $request = null, $param = '') use ($group, $field) {
            if ($field->isComputed()) {
                return true;
            }

            $contextValues = $this->collectContextValues($group, $request, $field, $value);

            if (!$this->shouldEvaluateField($group, $field, $contextValues)) {
                return true;
            }

            if ($field->isRequired() && $this->isEmpty($value)) {
                return new WP_Error(
                    'gm2_field_required',
                    sprintf('%s is required.', $field->getLabel()),
                    [ 'field' => $field->getKey() ]
                );
            }

            return $this->validatorRegistry->validate($field, $value, $contextValues);
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private function shouldEvaluateField(FieldGroupDefinition $group, FieldDefinition $field, array $values): bool
    {
        $conditions = $field->getConditions();
        if ($conditions === null) {
            return true;
        }

        $results = [];
        foreach ($conditions['items'] as $condition) {
            $otherKey = $condition['field'] ?? '';
            if (!is_string($otherKey) || $otherKey === '') {
                continue;
            }
            $operator = is_string($condition['operator'] ?? null) ? (string) $condition['operator'] : '==';
            $expected = $condition['value'] ?? null;
            $actual   = $values[$otherKey] ?? null;
            $results[] = $this->evaluateCondition($actual, $operator, $expected);
        }

        if ($results === []) {
            return true;
        }

        if ($conditions['relation'] === 'or') {
            return in_array(true, $results, true);
        }

        return !in_array(false, $results, true);
    }

    private function evaluateCondition(mixed $left, string $operator, mixed $right): bool
    {
        return match ($operator) {
            '===' => $left === $right,
            '=='  => $left == $right,
            '!='  => $left != $right,
            '!==' => $left !== $right,
            '>'   => is_numeric($left) && is_numeric($right) ? (float) $left > (float) $right : false,
            '>='  => is_numeric($left) && is_numeric($right) ? (float) $left >= (float) $right : false,
            '<'   => is_numeric($left) && is_numeric($right) ? (float) $left < (float) $right : false,
            '<='  => is_numeric($left) && is_numeric($right) ? (float) $left <= (float) $right : false,
            'in'  => is_array($right) ? in_array($left, $right, true) : false,
            'not_in' => is_array($right) ? !in_array($left, $right, true) : true,
            'contains' => $this->contains($left, $right),
            default => $left == $right,
        };
    }

    private function contains(mixed $left, mixed $right): bool
    {
        if (is_array($left)) {
            return in_array($right, $left, true);
        }
        if (is_string($left) && is_string($right)) {
            return str_contains($left, $right);
        }

        return false;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectContextValues(FieldGroupDefinition $group, mixed $request, FieldDefinition $field, mixed $value): array
    {
        $values = [];
        if ($request instanceof WP_REST_Request) {
            $meta = $request->get_param('meta');
            foreach ($group->getFields() as $otherField) {
                $paramValue = $request->get_param($otherField->getKey());
                if ($paramValue !== null) {
                    $values[$otherField->getKey()] = $paramValue;
                    continue;
                }
                if (is_array($meta) && array_key_exists($otherField->getKey(), $meta)) {
                    $values[$otherField->getKey()] = $meta[$otherField->getKey()];
                }
            }
        } elseif (is_array($request)) {
            $values = $request;
        }

        $values[$field->getKey()] = $value;

        return $values;
    }
}
