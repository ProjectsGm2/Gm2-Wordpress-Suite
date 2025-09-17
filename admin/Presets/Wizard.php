<?php

declare(strict_types=1);

namespace Gm2\Presets;

use WP_Error;

use function __;
use function add_action;
use function admin_url;
use function apply_filters;
use function array_filter;
use function array_values;
use function current_user_can;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function get_option;
use function in_array;
use function is_array;
use function is_string;
use function is_wp_error;
use function post_type_exists;
use function sanitize_key;
use function str_contains;
use function str_replace;
use function trailingslashit;
use function ucwords;
use function update_post_meta;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_insert_post;
use function wp_json_encode;
use function wp_localize_script;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_set_object_terms;
use function wp_slash;
use function wp_unslash;
use function wp_verify_nonce;
use function taxonomy_exists;

class Wizard
{
    public function run(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_gm2_presets_apply', [$this, 'ajaxApply']);
        add_action('wp_ajax_gm2_presets_import_elementor', [$this, 'ajaxImportElementor']);
    }

    public function enqueueAssets(string $hook): void
    {
        $screens = [
            'toplevel_page_gm2-custom-posts',
            'gm2-custom-posts_page_gm2_cpt_overview',
        ];
        if (!in_array($hook, $screens, true)) {
            return;
        }

        $manager = $this->getManager();
        if ($manager === null) {
            return;
        }

        $script = GM2_PLUGIN_DIR . 'admin/js/preset-wizard.js';
        wp_enqueue_script(
            'gm2-preset-wizard',
            GM2_PLUGIN_URL . 'admin/js/preset-wizard.js',
            ['wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch'],
            file_exists($script) ? filemtime($script) : GM2_VERSION,
            true
        );

        wp_localize_script('gm2-preset-wizard', 'gm2PresetWizard', [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'applyNonce'      => wp_create_nonce('gm2_presets_apply'),
            'importNonce'     => wp_create_nonce('gm2_presets_import_elementor'),
            'locked'          => $this->isModelLocked(),
            'hasExisting'     => $this->hasExistingDefinitions(),
            'capable'         => $this->canManage(),
            'elementorActive' => post_type_exists('elementor_library'),
            'presets'         => $this->preparePresetSummaries($manager),
            'i18n'            => [
                'heading'               => __('Blueprint Preset Wizard', 'gm2-wordpress-suite'),
                'selectPreset'          => __('Select a preset', 'gm2-wordpress-suite'),
                'descriptionHeading'    => __('Overview', 'gm2-wordpress-suite'),
                'postTypesHeading'      => __('Post types', 'gm2-wordpress-suite'),
                'taxonomiesHeading'     => __('Taxonomies', 'gm2-wordpress-suite'),
                'fieldGroupsHeading'    => __('Field groups', 'gm2-wordpress-suite'),
                'blockTemplatesHeading' => __('Block templates', 'gm2-wordpress-suite'),
                'elementorHeading'      => __('Elementor templates', 'gm2-wordpress-suite'),
                'defaultTermsHeading'   => __('Default terms', 'gm2-wordpress-suite'),
                'applyPreset'           => __('Apply preset', 'gm2-wordpress-suite'),
                'applyPresetAnyway'     => __('Apply preset anyway', 'gm2-wordpress-suite'),
                'cancel'                => __('Cancel', 'gm2-wordpress-suite'),
                'lockedMessage'         => __('Content model editing is locked for this environment.', 'gm2-wordpress-suite'),
                'missingCapability'     => __('You do not have permission to apply presets.', 'gm2-wordpress-suite'),
                'applySuccess'          => __('Preset applied.', 'gm2-wordpress-suite'),
                'applyError'            => __('Failed to apply the preset.', 'gm2-wordpress-suite'),
                'confirmTitle'          => __('Overwrite existing definitions?', 'gm2-wordpress-suite'),
                'confirmBody'           => __('Applying a preset will replace existing custom post types, taxonomies, field groups, and schema mappings.', 'gm2-wordpress-suite'),
                'importSuccess'         => __('Elementor templates imported.', 'gm2-wordpress-suite'),
                'importPartial'         => __('Some Elementor templates failed to import.', 'gm2-wordpress-suite'),
                'importError'           => __('Failed to import Elementor templates.', 'gm2-wordpress-suite'),
                'noTemplates'           => __('Preset does not bundle Elementor templates.', 'gm2-wordpress-suite'),
                'elementorInactive'     => __('Elementor must be active to import templates.', 'gm2-wordpress-suite'),
                'noPresets'             => __('No presets are currently available.', 'gm2-wordpress-suite'),
                'templateUnavailable'   => __('Template bundle is not included with this preset.', 'gm2-wordpress-suite'),
            ],
        ]);
    }

    public function ajaxApply(): void
    {
        if (!$this->canManage()) {
            wp_send_json_error([
                'code'    => 'permission',
                'message' => __('You do not have permission to apply presets.', 'gm2-wordpress-suite'),
            ]);
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'gm2_presets_apply')) {
            wp_send_json_error([
                'code'    => 'nonce',
                'message' => __('Security check failed. Please refresh and try again.', 'gm2-wordpress-suite'),
            ]);
        }

        $slug = sanitize_key($_POST['preset'] ?? '');
        if ($slug === '') {
            wp_send_json_error([
                'code'    => 'preset_missing',
                'message' => __('No preset selected.', 'gm2-wordpress-suite'),
            ]);
        }

        $manager = $this->getManager();
        if ($manager === null) {
            wp_send_json_error([
                'code'    => 'manager_missing',
                'message' => __('Preset manager is unavailable.', 'gm2-wordpress-suite'),
            ]);
        }

        $force = !empty($_POST['force']);

        $result = $manager->apply($slug, $force);
        if (is_wp_error($result)) {
            wp_send_json_error([
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ]);
        }

        wp_send_json_success([
            'message' => __('Preset applied.', 'gm2-wordpress-suite'),
        ]);
    }

    public function ajaxImportElementor(): void
    {
        if (!$this->canManage()) {
            wp_send_json_error([
                'code'    => 'permission',
                'message' => __('You do not have permission to import templates.', 'gm2-wordpress-suite'),
            ]);
        }

        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'gm2_presets_import_elementor')) {
            wp_send_json_error([
                'code'    => 'nonce',
                'message' => __('Security check failed. Please refresh and try again.', 'gm2-wordpress-suite'),
            ]);
        }

        $slug = sanitize_key($_POST['preset'] ?? '');
        if ($slug === '') {
            wp_send_json_error([
                'code'    => 'preset_missing',
                'message' => __('No preset selected.', 'gm2-wordpress-suite'),
            ]);
        }

        $requested = $_POST['templates'] ?? [];
        if (!is_array($requested)) {
            $requested = [];
        } else {
            $requested = array_values(array_filter(array_map('sanitize_key', wp_unslash($requested))));
        }

        if (!$requested) {
            wp_send_json_success([
                'message' => __('No Elementor templates selected.', 'gm2-wordpress-suite'),
                'results' => [],
            ]);
        }

        $manager = $this->getManager();
        if ($manager === null) {
            wp_send_json_error([
                'code'    => 'manager_missing',
                'message' => __('Preset manager is unavailable.', 'gm2-wordpress-suite'),
            ]);
        }

        $blueprint = $manager->get($slug);
        if (!is_array($blueprint)) {
            wp_send_json_error([
                'code'    => 'preset_missing',
                'message' => __('Preset not found.', 'gm2-wordpress-suite'),
            ]);
        }

        $basePath = $manager->getPath($slug);
        if ($basePath === null) {
            wp_send_json_error([
                'code'    => 'preset_path_missing',
                'message' => __('Preset path could not be resolved.', 'gm2-wordpress-suite'),
            ]);
        }

        $results = [];
        $errors = false;

        foreach ($requested as $templateKey) {
            $import = $this->importTemplate($blueprint, $basePath, $templateKey);
            if (is_wp_error($import)) {
                $results[] = [
                    'key'     => $templateKey,
                    'status'  => 'error',
                    'message' => $import->get_error_message(),
                ];
                $errors = true;
            } else {
                $results[] = [
                    'key'    => $templateKey,
                    'status' => 'success',
                    'id'     => $import,
                ];
            }
        }

        $response = [
            'message' => $errors
                ? __('Some Elementor templates failed to import.', 'gm2-wordpress-suite')
                : __('Elementor templates imported.', 'gm2-wordpress-suite'),
            'results' => $results,
        ];

        if ($errors) {
            wp_send_json_error($response);
        }

        wp_send_json_success($response);
    }

    private function preparePresetSummaries(PresetManager $manager): array
    {
        $summaries = [];
        foreach ($manager->all() as $slug => $blueprint) {
            if (!is_array($blueprint)) {
                continue;
            }
            $label = isset($blueprint['label']) && is_string($blueprint['label']) ? $blueprint['label'] : $this->toLabel($slug);
            $description = isset($blueprint['description']) && is_string($blueprint['description']) ? $blueprint['description'] : '';

            $postTypes = [];
            foreach ($blueprint['post_types'] ?? [] as $ptSlug => $config) {
                if (!is_array($config)) {
                    continue;
                }
                $ptLabel = '';
                if (isset($config['labels']['name']) && is_string($config['labels']['name'])) {
                    $ptLabel = $config['labels']['name'];
                } elseif (isset($config['label']) && is_string($config['label'])) {
                    $ptLabel = $config['label'];
                }
                if ($ptLabel === '') {
                    $ptLabel = $this->toLabel($ptSlug);
                }
                $postTypes[] = [
                    'slug'   => $ptSlug,
                    'label'  => $ptLabel,
                    'fields' => isset($config['fields']) && is_array($config['fields']) ? count($config['fields']) : 0,
                ];
            }

            $taxonomies = [];
            foreach ($blueprint['taxonomies'] ?? [] as $taxSlug => $config) {
                if (!is_array($config)) {
                    continue;
                }
                $taxLabel = '';
                if (isset($config['labels']['name']) && is_string($config['labels']['name'])) {
                    $taxLabel = $config['labels']['name'];
                } elseif (isset($config['label']) && is_string($config['label'])) {
                    $taxLabel = $config['label'];
                }
                if ($taxLabel === '') {
                    $taxLabel = $this->toLabel($taxSlug);
                }
                $taxonomies[] = [
                    'slug'  => $taxSlug,
                    'label' => $taxLabel,
                ];
            }

            $fieldGroups = [];
            $groups = $blueprint['field_groups'] ?? [];
            if (!$groups && isset($blueprint['fields']['groups']) && is_array($blueprint['fields']['groups'])) {
                $groups = $blueprint['fields']['groups'];
            }
            if (is_array($groups)) {
                foreach ($groups as $group) {
                    if (!is_array($group)) {
                        continue;
                    }
                    $title = isset($group['title']) && is_string($group['title']) ? $group['title'] : '';
                    if ($title === '' && isset($group['key']) && is_string($group['key'])) {
                        $title = $this->toLabel($group['key']);
                    }
                    $fieldGroups[] = [
                        'title' => $title,
                        'count' => isset($group['fields']) && is_array($group['fields']) ? count($group['fields']) : 0,
                    ];
                }
            }

            $defaultTerms = [];
            foreach ($blueprint['default_terms'] ?? [] as $tax => $terms) {
                $defaultTerms[] = [
                    'taxonomy' => $tax,
                    'count'    => is_array($terms) ? count($terms) : 0,
                ];
            }

            $blockTemplates = [];
            foreach ($blueprint['templates'] ?? [] as $templateKey => $templateData) {
                if (!is_array($templateData)) {
                    continue;
                }
                $blockTemplates[] = [
                    'key'         => $templateKey,
                    'description' => isset($templateData['description']) && is_string($templateData['description'])
                        ? $templateData['description']
                        : '',
                ];
            }

            $elementorTemplates = $this->mapElementorTemplates($manager, $slug, $blueprint);

            $summaries[] = [
                'slug'               => $slug,
                'label'              => $label,
                'description'        => $description,
                'postTypes'          => $postTypes,
                'taxonomies'         => $taxonomies,
                'fieldGroups'        => $fieldGroups,
                'defaultTerms'       => $defaultTerms,
                'blockTemplates'     => $blockTemplates,
                'elementorTemplates' => $elementorTemplates,
            ];
        }

        return $summaries;
    }

    private function mapElementorTemplates(PresetManager $manager, string $slug, array $blueprint): array
    {
        $templates = $blueprint['elementor']['templates'] ?? [];
        if (!is_array($templates)) {
            return [];
        }
        $result = [];
        foreach ($templates as $key => $template) {
            if (!is_array($template)) {
                continue;
            }
            $description = isset($template['description']) && is_string($template['description'])
                ? $template['description']
                : '';
            $type = isset($template['type']) && is_string($template['type']) ? $template['type'] : '';
            $file = isset($template['file']) && is_string($template['file']) ? $template['file'] : '';
            $result[] = [
                'key'      => $key,
                'label'    => isset($template['label']) && is_string($template['label'])
                    ? $template['label']
                    : $this->toLabel($key),
                'description' => $description,
                'type'     => $type,
                'hasFile'  => $file !== '' && $this->templateFileExists($manager, $slug, $file),
            ];
        }
        return $result;
    }

    private function templateFileExists(PresetManager $manager, string $slug, string $file): bool
    {
        if ($file === '' || str_contains($file, '..')) {
            return false;
        }
        $base = $manager->getPath($slug);
        if ($base === null) {
            return false;
        }
        $path = trailingslashit($base) . ltrim($file, '/');
        return file_exists($path);
    }

    private function importTemplate(array $blueprint, string $basePath, string $templateKey)
    {
        if (!post_type_exists('elementor_library')) {
            return new WP_Error(
                'gm2_elementor_inactive',
                __('Elementor templates require the Elementor plugin to be active.', 'gm2-wordpress-suite')
            );
        }

        $template = $blueprint['elementor']['templates'][$templateKey] ?? null;
        if (!is_array($template)) {
            return new WP_Error(
                'gm2_template_missing',
                __('The requested Elementor template is not defined in the preset.', 'gm2-wordpress-suite')
            );
        }

        $file = $template['file'] ?? '';
        if (!is_string($file) || $file === '' || str_contains($file, '..')) {
            return new WP_Error(
                'gm2_template_file_missing',
                __('The Elementor template bundle could not be located.', 'gm2-wordpress-suite')
            );
        }

        $path = trailingslashit($basePath) . ltrim($file, '/');
        if (!file_exists($path)) {
            return new WP_Error(
                'gm2_template_file_missing',
                __('The Elementor template bundle could not be located.', 'gm2-wordpress-suite')
            );
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return new WP_Error(
                'gm2_template_unreadable',
                __('Unable to read the Elementor template bundle.', 'gm2-wordpress-suite')
            );
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return new WP_Error(
                'gm2_template_invalid',
                __('The Elementor template bundle contains invalid JSON.', 'gm2-wordpress-suite')
            );
        }

        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : $this->toLabel($templateKey);
        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : ($template['type'] ?? 'section');
        if (!is_string($type)) {
            $type = 'section';
        }

        $postId = wp_insert_post([
            'post_title'  => $title,
            'post_type'   => 'elementor_library',
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($postId)) {
            return $postId;
        }
        if (!$postId) {
            return new WP_Error(
                'gm2_template_insert_failed',
                __('Failed to save the Elementor template.', 'gm2-wordpress-suite')
            );
        }

        $content = $data['content'] ?? [];
        if (!is_array($content)) {
            $content = [];
        }

        update_post_meta($postId, '_elementor_edit_mode', 'builder');
        update_post_meta($postId, '_elementor_template_type', $type);
        if (isset($data['version']) && is_string($data['version'])) {
            update_post_meta($postId, '_elementor_version', $data['version']);
        }
        update_post_meta($postId, '_elementor_data', wp_slash(wp_json_encode($content)));

        if (taxonomy_exists('elementor_library_type') && $type !== '') {
            wp_set_object_terms($postId, $type, 'elementor_library_type', false);
        }

        return (int) $postId;
    }

    private function canManage(): bool
    {
        if ($this->isModelLocked()) {
            return false;
        }

        return current_user_can('manage_options') || current_user_can('gm2_manage_cpts');
    }

    private function isModelLocked(): bool
    {
        return (bool) get_option('gm2_model_locked');
    }

    private function hasExistingDefinitions(): bool
    {
        $config = get_option('gm2_custom_posts_config', []);
        if (is_array($config)) {
            if (!empty($config['post_types']) || !empty($config['taxonomies'])) {
                return true;
            }
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

    private function getManager(): ?PresetManager
    {
        $manager = apply_filters('gm2/presets/manager', null);
        return $manager instanceof PresetManager ? $manager : null;
    }

    private function toLabel(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
