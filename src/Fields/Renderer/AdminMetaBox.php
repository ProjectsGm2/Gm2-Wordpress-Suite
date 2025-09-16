<?php

namespace Gm2\Fields\Renderer;

use Gm2\Fields\FieldDefinition;
use Gm2\Fields\FieldGroupDefinition;

final class AdminMetaBox
{
    /**
     * @var array<string, FieldGroupDefinition>
     */
    private array $groups = [];

    private bool $hooksRegistered = false;

    public function addGroup(FieldGroupDefinition $group): void
    {
        $this->groups[$group->getKey()] = $group;
        if (!$this->hooksRegistered) {
            $this->registerHooks();
            $this->hooksRegistered = true;
        }
    }

    private function registerHooks(): void
    {
        add_action('add_meta_boxes', [ $this, 'registerMetaBoxes' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ]);
        add_action('enqueue_block_editor_assets', [ $this, 'enqueueBlockEditorAssets' ]);
    }

    public function registerMetaBoxes(string $postType): void
    {
        foreach ($this->groups as $group) {
            if (!in_array($postType, $group->getPostTypes(), true)) {
                continue;
            }

            add_meta_box(
                'gm2_fields_' . $group->getKey(),
                $group->getTitle(),
                function ($post) use ($group) {
                    $this->renderGroupMetaBox($group, (int) $post->ID);
                },
                $postType,
                'side',
                'default'
            );
        }
    }

    public function enqueueAdminAssets(): void
    {
        if ($this->groups === []) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->buildConditionalScript(), 'after');
    }

    public function enqueueBlockEditorAssets(): void
    {
        if ($this->groups === []) {
            return;
        }

        $data = $this->prepareGroupData();
        wp_enqueue_script('wp-blocks');
        wp_add_inline_script('wp-blocks', 'window.GM2FieldGroups = ' . wp_json_encode($data) . ';', 'before');
    }

    private function renderGroupMetaBox(FieldGroupDefinition $group, int $postId): void
    {
        wp_nonce_field('gm2_fields_' . $group->getKey(), 'gm2_fields_nonce_' . $group->getKey());

        echo '<div class="gm2-field-group" data-field-group="' . esc_attr($group->getKey()) . '">';
        foreach ($group->getFields() as $field) {
            $value = get_post_meta($postId, $field->getKey(), true);
            $this->renderFieldControl($field, $value);
        }
        echo '</div>';
    }

    private function renderFieldControl(FieldDefinition $field, mixed $value): void
    {
        $conditions = $field->getConditions();
        $dataAttr   = $conditions ? esc_attr(wp_json_encode($conditions)) : '';
        $id         = 'gm2_field_' . $field->getKey();

        echo '<p class="gm2-field" data-field-key="' . esc_attr($field->getKey()) . '" data-conditions="' . $dataAttr . '">';
        echo '<label for="' . esc_attr($id) . '">' . esc_html($field->getLabel()) . '</label>';
        echo '<input type="text" class="widefat" name="' . esc_attr($field->getKey()) . '" id="' . esc_attr($id) . '" value="' . esc_attr(is_scalar($value) ? (string) $value : '') . '" />';
        echo '</p>';
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareGroupData(): array
    {
        $data = [];
        foreach ($this->groups as $group) {
            $fields = [];
            foreach ($group->getFields() as $field) {
                $fields[] = [
                    'key'        => $field->getKey(),
                    'label'      => $field->getLabel(),
                    'type'       => $field->getType()->getName(),
                    'conditions' => $field->getConditions(),
                ];
            }
            $data[] = [
                'key'        => $group->getKey(),
                'title'      => $group->getTitle(),
                'post_types' => $group->getPostTypes(),
                'fields'     => $fields,
            ];
        }

        return $data;
    }

    private function buildConditionalScript(): string
    {
        $data = $this->prepareGroupData();
        $json = wp_json_encode($data);

        return 'window.GM2FieldGroups = ' . $json . ';' .
            'jQuery(function($){' .
            'const groups = window.GM2FieldGroups || [];'
            . 'groups.forEach(function(group){'
            . 'const container = $(".gm2-field-group[data-field-group="+group.key+"]");'
            . 'group.fields.forEach(function(field){'
            . 'const el = container.find("[data-field-key="+field.key+"]");'
            . 'if(!field.conditions){return;}'
            . 'const evaluate = function(){'
            . 'let visible = field.conditions.items.every(function(condition){'
            . 'const other = container.find("[name="+condition.field+"]");'
            . 'if(!other.length){return true;}'
            . 'const value = other.val();'
            . 'switch(condition.operator){case "==": return value == condition.value;'
            . 'case "!=": return value != condition.value; default: return true;}'
            . '});'
            . 'if(field.conditions.relation === "or"){visible = false; field.conditions.items.forEach(function(condition){'
            . 'const other = container.find("[name="+condition.field+"]");'
            . 'if(!other.length){return;}'
            . 'const value = other.val();'
            . 'if((condition.operator === "==" && value == condition.value) || (condition.operator === "!=" && value != condition.value)){visible = true;}'
            . '});}'
            . 'el.toggle(visible);'
            . '};'
            . 'container.on("change", "input, select", evaluate);'
            . 'evaluate();'
            . '});'
            . '});'
            . '});';
    }
}
