<?php

declare(strict_types=1);

use Gm2\Elementor\Controls\MetaKeySelect;
use Gm2\Elementor\Controls\PostTypeSelect;
use Gm2\Elementor\Controls\Price;
use Gm2\Elementor\Controls\TaxonomyTermMulti;
use Gm2\Elementor\Controls\Unit;

class ControlsTemplateTest extends WP_UnitTestCase
{
    public function test_post_type_control_template_contains_ajax_action(): void
    {
        $control = new PostTypeSelect();
        ob_start();
        $control->content_template();
        $template = ob_get_clean();

        $this->assertStringContainsString('data-action="gm2_elementor_post_types"', $template);
        $this->assertStringContainsString('data-selected', $template);
    }

    public function test_taxonomy_control_marks_taxonomy_dependency(): void
    {
        $control = new TaxonomyTermMulti();
        ob_start();
        $control->content_template();
        $template = ob_get_clean();

        $this->assertStringContainsString('data-taxonomy-control', $template);
        $this->assertStringContainsString('gm2_elementor_taxonomy_terms', $template);
    }

    public function test_meta_key_control_includes_post_type_reference(): void
    {
        $control = new MetaKeySelect();
        ob_start();
        $control->content_template();
        $template = ob_get_clean();

        $this->assertStringContainsString('data-post-type-control', $template);
        $this->assertStringContainsString('gm2_elementor_meta_keys', $template);
    }

    public function test_unit_control_uses_nested_settings(): void
    {
        $control = new Unit();
        ob_start();
        $control->content_template();
        $template = ob_get_clean();

        $this->assertStringContainsString('[value]', $template);
        $this->assertStringContainsString('[unit]', $template);
    }

    public function test_price_control_outputs_key_and_range_inputs(): void
    {
        $control = new Price();
        ob_start();
        $control->content_template();
        $template = ob_get_clean();

        $this->assertStringContainsString('[key]', $template);
        $this->assertStringContainsString('[min]', $template);
        $this->assertStringContainsString('[max]', $template);
    }
}
