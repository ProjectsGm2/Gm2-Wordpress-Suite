<?php

namespace Gm2\Content\Registry;

use InvalidArgumentException;
use RuntimeException;

final class TaxonomyRegistry
{
    public function register(
        string $slug,
        string $singular,
        string $plural,
        array $objectTypes,
        array $args = []
    ): void {
        if (!function_exists('register_taxonomy')) {
            return;
        }

        $slug = $this->resolveSlug($slug, $singular, $plural);

        if (taxonomy_exists($slug)) {
            throw new RuntimeException(sprintf('Taxonomy "%s" already exists.', $slug));
        }

        $objectTypes = $this->prepareObjectTypes($objectTypes);

        $labels = $this->buildLabels($singular, $plural, (array) ($args['labels'] ?? []));
        $rewrite = $this->prepareRewrite((array) ($args['rewrite'] ?? []));
        $hierarchical = $args['hierarchical'] ?? null;
        $capabilityType = isset($args['capability_type']) ? (string) $args['capability_type'] : '';

        unset($args['labels'], $args['rewrite'], $args['capability_type']);

        $args['labels'] = $labels;
        $args['show_in_rest'] = true;

        if ($rewrite !== []) {
            $args['rewrite'] = $rewrite;
        }

        if ($hierarchical !== null) {
            $args['hierarchical'] = (bool) $hierarchical;
        }

        if ($capabilityType !== '') {
            $sanitizedType = sanitize_key($capabilityType);
            if ($sanitizedType !== '') {
                $args['capabilities'] = $args['capabilities'] ?? $this->generateCapabilities($sanitizedType);
            }
        }

        $args = apply_filters('gm2/content/taxonomy_args', $args, $slug, $objectTypes);

        register_taxonomy($slug, $objectTypes, $args);
    }

    private function resolveSlug(string $slug, string $singular, string $plural): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            $slug = $this->generateSlugFromLabels($singular, $plural);
        }

        $slug = sanitize_key($slug);

        $this->assertValidKey($slug, 'taxonomy');

        return $slug;
    }

    private function generateSlugFromLabels(string $singular, string $plural): string
    {
        $label = trim($singular) !== '' ? $singular : $plural;

        if ($label === '') {
            throw new InvalidArgumentException('Taxonomy slug could not be determined from empty labels.');
        }

        $generated = sanitize_title($label);

        if ($generated === '') {
            throw new InvalidArgumentException('Taxonomy slug could not be generated from the provided labels.');
        }

        return $generated;
    }

    private function assertValidKey(string $key, string $type): void
    {
        if ($key === '') {
            throw new InvalidArgumentException(sprintf('The %s key cannot be empty.', $type));
        }

        if (strlen($key) > 20) {
            throw new InvalidArgumentException(sprintf('The %s key "%s" exceeds the maximum length of 20 characters.', $type, $key));
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $key)) {
            throw new InvalidArgumentException(sprintf('The %s key "%s" must contain only lowercase letters, numbers, underscores, or dashes.', $type, $key));
        }
    }

    private function buildLabels(string $singular, string $plural, array $overrides = []): array
    {
        $singular = sanitize_text_field($singular);
        $plural = sanitize_text_field($plural);

        $defaults = [
            'name' => $plural,
            'singular_name' => $singular,
            'search_items' => sprintf(__('Search %s', 'gm2-wordpress-suite'), $plural),
            'all_items' => sprintf(__('All %s', 'gm2-wordpress-suite'), $plural),
            'parent_item' => sprintf(__('Parent %s', 'gm2-wordpress-suite'), $singular),
            'parent_item_colon' => sprintf(__('Parent %s:', 'gm2-wordpress-suite'), $singular),
            'edit_item' => sprintf(__('Edit %s', 'gm2-wordpress-suite'), $singular),
            'view_item' => sprintf(__('View %s', 'gm2-wordpress-suite'), $singular),
            'update_item' => sprintf(__('Update %s', 'gm2-wordpress-suite'), $singular),
            'add_new_item' => sprintf(__('Add New %s', 'gm2-wordpress-suite'), $singular),
            'new_item_name' => sprintf(__('New %s Name', 'gm2-wordpress-suite'), $singular),
            'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'gm2-wordpress-suite'), $plural),
            'add_or_remove_items' => sprintf(__('Add or remove %s', 'gm2-wordpress-suite'), $plural),
            'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'gm2-wordpress-suite'), $plural),
            'not_found' => sprintf(__('No %s found.', 'gm2-wordpress-suite'), $plural),
            'no_terms' => sprintf(__('No %s', 'gm2-wordpress-suite'), $plural),
            'items_list_navigation' => sprintf(__('%s list navigation', 'gm2-wordpress-suite'), $plural),
            'items_list' => sprintf(__('%s list', 'gm2-wordpress-suite'), $plural),
            'back_to_items' => sprintf(__('← Back to %s', 'gm2-wordpress-suite'), $plural),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * @param array<string, mixed> $rewrite
     * @return array<string, mixed>
     */
    private function prepareRewrite(array $rewrite): array
    {
        $prepared = [];
        if (isset($rewrite['slug']) && $rewrite['slug'] !== '') {
            $prepared['slug'] = sanitize_title($rewrite['slug']);
        }
        if (isset($rewrite['with_front'])) {
            $prepared['with_front'] = (bool) $rewrite['with_front'];
        }
        if (isset($rewrite['hierarchical'])) {
            $prepared['hierarchical'] = (bool) $rewrite['hierarchical'];
        }

        return $prepared;
    }

    /**
     * @param string[] $objectTypes
     * @return string[]
     */
    private function prepareObjectTypes(array $objectTypes): array
    {
        $prepared = [];
        foreach ($objectTypes as $objectType) {
            if (!is_string($objectType)) {
                continue;
            }
            $prepared[] = sanitize_key($objectType);
        }

        return array_values(array_unique(array_filter($prepared)));
    }

    /**
     * @return array<string, string>
     */
    private function generateCapabilities(string $capabilityType): array
    {
        return [
            'manage_terms' => 'manage_' . $capabilityType . '_terms',
            'edit_terms' => 'edit_' . $capabilityType . '_terms',
            'delete_terms' => 'delete_' . $capabilityType . '_terms',
            'assign_terms' => 'assign_' . $capabilityType . '_terms',
        ];
    }
}
