<?php

namespace Gm2\Content\Registry;

use Gm2\Content\Model\Definition;

final class PostTypeRegistry
{
    public function register(Definition $definition): void
    {
        if (!function_exists('register_post_type')) {
            return;
        }

        $slug = sanitize_key($definition->getSlug());
        if ($slug === '') {
            return;
        }

        $labels = $this->buildLabels($definition);
        $supports = $this->prepareSupports($definition->getSupports());
        $rewrite = $this->prepareRewrite($definition->getRewrite());
        $taxonomies = $this->prepareTaxonomies($definition->getTaxonomies());
        $menuIcon = $definition->getMenuIcon();
        $hasArchive = $definition->getHasArchive();
        $capabilityType = $definition->getCapabilityType() !== ''
            ? sanitize_key($definition->getCapabilityType())
            : 'post';

        $args = $definition->getArguments();
        $args['labels'] = array_merge($args['labels'] ?? [], $labels);
        $args['show_in_rest'] = true;
        $args['map_meta_cap'] = true;
        $args['capability_type'] = $capabilityType;

        if ($supports !== []) {
            $args['supports'] = $supports;
        }

        if ($rewrite !== []) {
            $args['rewrite'] = $rewrite;
        }

        if ($taxonomies !== []) {
            $args['taxonomies'] = $taxonomies;
        }

        if ($menuIcon !== null && $menuIcon !== '') {
            $args['menu_icon'] = sanitize_text_field($menuIcon);
        }

        if ($hasArchive !== null) {
            $args['has_archive'] = is_string($hasArchive)
                ? sanitize_title($hasArchive)
                : (bool) $hasArchive;
        }

        $args = apply_filters('gm2/content/post_type_args', $args, $definition);
        $args = apply_filters('gm2_register_post_type_args', $args, $slug, []);

        register_post_type($slug, $args);

        $this->bindTaxonomies($slug, $taxonomies);
    }

    private function buildLabels(Definition $definition): array
    {
        $singular = sanitize_text_field($definition->getSingular());
        $plural = sanitize_text_field($definition->getPlural());

        $defaults = [
            'name' => $plural,
            'singular_name' => $singular,
            'menu_name' => $plural,
            'name_admin_bar' => $singular,
            'add_new' => sprintf(__('Add New %s', 'gm2-wordpress-suite'), $singular),
            'add_new_item' => sprintf(__('Add New %s', 'gm2-wordpress-suite'), $singular),
            'edit_item' => sprintf(__('Edit %s', 'gm2-wordpress-suite'), $singular),
            'new_item' => sprintf(__('New %s', 'gm2-wordpress-suite'), $singular),
            'view_item' => sprintf(__('View %s', 'gm2-wordpress-suite'), $singular),
            'view_items' => sprintf(__('View %s', 'gm2-wordpress-suite'), $plural),
            'search_items' => sprintf(__('Search %s', 'gm2-wordpress-suite'), $plural),
            'not_found' => sprintf(__('No %s found.', 'gm2-wordpress-suite'), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in Trash.', 'gm2-wordpress-suite'), $plural),
            'all_items' => sprintf(__('All %s', 'gm2-wordpress-suite'), $plural),
            'archives' => sprintf(__('%s Archives', 'gm2-wordpress-suite'), $singular),
            'attributes' => sprintf(__('%s Attributes', 'gm2-wordpress-suite'), $singular),
            'insert_into_item' => sprintf(__('Insert into %s', 'gm2-wordpress-suite'), $singular),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'gm2-wordpress-suite'), $singular),
            'featured_image' => __('Featured image', 'gm2-wordpress-suite'),
            'set_featured_image' => __('Set featured image', 'gm2-wordpress-suite'),
            'remove_featured_image' => __('Remove featured image', 'gm2-wordpress-suite'),
            'use_featured_image' => __('Use as featured image', 'gm2-wordpress-suite'),
            'filter_items_list' => sprintf(__('Filter %s list', 'gm2-wordpress-suite'), $plural),
            'items_list_navigation' => sprintf(__('%s list navigation', 'gm2-wordpress-suite'), $plural),
            'items_list' => sprintf(__('%s list', 'gm2-wordpress-suite'), $plural),
        ];

        return array_merge($defaults, $definition->getLabels());
    }

    /**
     * @param string[] $supports
     * @return string[]
     */
    private function prepareSupports(array $supports): array
    {
        $sanitized = [];
        foreach ($supports as $support) {
            if (!is_string($support)) {
                continue;
            }
            $sanitized[] = sanitize_key($support);
        }

        return array_values(array_unique(array_filter($sanitized)));
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
     * @param string[] $taxonomies
     * @return string[]
     */
    private function prepareTaxonomies(array $taxonomies): array
    {
        $prepared = [];
        foreach ($taxonomies as $taxonomy) {
            if (!is_string($taxonomy)) {
                continue;
            }
            $prepared[] = sanitize_key($taxonomy);
        }

        return array_values(array_unique(array_filter($prepared)));
    }

    /**
     * @param string[] $taxonomies
     */
    private function bindTaxonomies(string $slug, array $taxonomies): void
    {
        if ($taxonomies === []) {
            return;
        }

        foreach ($taxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                register_taxonomy_for_object_type($taxonomy, $slug);
            }
        }
    }
}
