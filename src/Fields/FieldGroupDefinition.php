<?php

namespace Gm2\Fields;

use InvalidArgumentException;

final class FieldGroupDefinition
{
    /**
     * @var array<string, FieldDefinition>
     */
    private array $fields;

    /**
     * @var array{post: string[], term: string[], user: bool}
     */
    private array $contexts;

    /**
     * @var array<string, array<int, string>>
     */
    private array $computedGraph;

    /**
     * @param array<string, mixed>              $config
     * @param array<string, FieldDefinition>    $fields
     */
    public function __construct(
        private readonly string $key,
        private readonly array $config,
        array $fields
    ) {
        $this->fields        = $fields;
        $this->contexts      = $this->normalizeContexts($config);
        $this->computedGraph = $this->buildComputedGraph();
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        $title = $this->config['title'] ?? $this->key;

        return is_string($title) ? $title : $this->key;
    }

    /**
     * @return array<string, FieldDefinition>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $key): FieldDefinition
    {
        if (!isset($this->fields[$key])) {
            throw new InvalidArgumentException(sprintf('Field "%s" is not defined in group "%s".', $key, $this->key));
        }

        return $this->fields[$key];
    }

    /**
     * @return string[]
     */
    public function getPostTypes(): array
    {
        return $this->contexts['post'];
    }

    /**
     * @return string[]
     */
    public function getTaxonomies(): array
    {
        return $this->contexts['term'];
    }

    public function appliesToUsers(): bool
    {
        return $this->contexts['user'];
    }

    /**
     * @return array{post: string[], term: string[], user: bool}
     */
    public function getContexts(): array
    {
        return $this->contexts;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getComputedDependencyGraph(): array
    {
        return $this->computedGraph;
    }

    private function buildComputedGraph(): array
    {
        $graph = [];
        foreach ($this->fields as $field) {
            $deps = $field->getComputedDependencies();
            if ($deps === []) {
                continue;
            }
            $graph[$field->getKey()] = $deps;
        }

        $this->assertValidDependencies($graph);

        return $graph;
    }

    private function assertValidDependencies(array $graph): void
    {
        foreach ($graph as $fieldKey => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (!isset($this->fields[$dependency])) {
                    throw new InvalidArgumentException(sprintf(
                        'Field "%s" depends on undefined field "%s".',
                        $fieldKey,
                        $dependency
                    ));
                }
            }
        }

        $visited = [];
        $stack   = [];
        foreach (array_keys($graph) as $fieldKey) {
            if (!isset($visited[$fieldKey])) {
                $this->assertAcyclic($fieldKey, $graph, $visited, $stack);
            }
        }
    }

    /**
     * @param array<string, array<int, string>> $graph
     * @param array<string, bool>               $visited
     * @param array<string, bool>               $stack
     */
    private function assertAcyclic(string $fieldKey, array $graph, array &$visited, array &$stack): void
    {
        $visited[$fieldKey] = true;
        $stack[$fieldKey]   = true;

        foreach ($graph[$fieldKey] ?? [] as $dependency) {
            if (!isset($visited[$dependency])) {
                $this->assertAcyclic($dependency, $graph, $visited, $stack);
                continue;
            }

            if ($stack[$dependency] ?? false) {
                throw new InvalidArgumentException(sprintf(
                    'Detected circular dependency involving "%s".',
                    $dependency
                ));
            }
        }

        $stack[$fieldKey] = false;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{post: string[], term: string[], user: bool}
     */
    private function normalizeContexts(array $config): array
    {
        $contexts = $config['contexts'] ?? [];
        if (!is_array($contexts)) {
            $contexts = [];
        }

        if (isset($config['post_types']) && is_array($config['post_types'])) {
            $contexts['post'] = $config['post_types'];
        }

        if (isset($config['taxonomies']) && is_array($config['taxonomies'])) {
            $contexts['term'] = $config['taxonomies'];
        }

        if (array_key_exists('users', $config)) {
            $contexts['user'] = (bool) $config['users'];
        }

        $postTypes = array_values(array_unique(array_filter(
            (array) ($contexts['post'] ?? []),
            static fn ($postType) => is_string($postType) && $postType !== ''
        )));

        $taxonomies = array_values(array_unique(array_filter(
            (array) ($contexts['term'] ?? []),
            static fn ($taxonomy) => is_string($taxonomy) && $taxonomy !== ''
        )));

        $userContext = $contexts['user'] ?? false;
        if (is_array($userContext)) {
            $userContext = $userContext !== [];
        }

        return [
            'post' => $postTypes,
            'term' => $taxonomies,
            'user' => (bool) $userContext,
        ];
    }
}
