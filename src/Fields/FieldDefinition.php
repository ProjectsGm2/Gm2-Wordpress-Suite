<?php

namespace Gm2\Fields;

use Gm2\Fields\Types\FieldTypeInterface;

final class FieldDefinition
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private readonly string $key,
        private readonly FieldTypeInterface $type,
        private readonly array $settings = []
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): FieldTypeInterface
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getLabel(): string
    {
        $label = $this->settings['label'] ?? $this->key;

        return is_string($label) ? $label : $this->key;
    }

    public function getDescription(): ?string
    {
        $description = $this->settings['description'] ?? null;

        return is_string($description) ? $description : null;
    }

    public function getDefault(): mixed
    {
        return $this->settings['default'] ?? null;
    }

    public function isRequired(): bool
    {
        return (bool) ($this->settings['required'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRestSettings(): array
    {
        $settings = $this->settings['rest'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getOptions(): array
    {
        $options = $this->settings['options'] ?? [];
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $value => $label) {
            if (is_int($value)) {
                $value = (string) $value;
            }
            if (!is_string($value)) {
                continue;
            }
            $normalized[$value] = is_string($label) ? $label : (string) $label;
        }

        return $normalized;
    }

    /**
     * @return array{relation: string, items: array<int, array<string, mixed>>}|null
     */
    public function getConditions(): ?array
    {
        $conditions = $this->settings['conditions'] ?? [];
        if (!is_array($conditions) || $conditions === []) {
            return null;
        }

        $relation = strtolower((string) ($conditions['relation'] ?? 'and'));
        if (!in_array($relation, [ 'and', 'or' ], true)) {
            $relation = 'and';
        }

        $items = [];
        foreach ($conditions as $key => $condition) {
            if ($key === 'relation') {
                continue;
            }
            if (!is_array($condition) || !isset($condition['field'])) {
                continue;
            }
            $items[] = $condition;
        }

        if ($items === []) {
            return null;
        }

        return [
            'relation' => $relation,
            'items'    => $items,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getComputedDependencies(): array
    {
        $config = $this->settings['computed'] ?? [];
        if (!is_array($config)) {
            return [];
        }

        $deps = $config['dependencies'] ?? [];
        if (!is_array($deps)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($dependency) => is_string($dependency) ? $dependency : null,
            $deps
        )));
    }

    public function isComputed(): bool
    {
        return $this->type->getName() === 'computed';
    }
}
