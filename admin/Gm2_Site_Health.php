<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Site_Health {
    public function run() {
        add_filter('site_status_tests', [ $this, 'register_tests' ]);
    }

    public function register_tests($tests) {
        $tests['direct']['gm2_suite_diagnostics'] = [
            'label' => __('Gm2 Suite Diagnostics', 'gm2-wordpress-suite'),
            'test'  => [ $this, 'perform_test' ],
        ];
        return $tests;
    }

    public function perform_test() {
        $diag = new Gm2_Diagnostics();
        $diag->diagnose();
        $conflicts = $diag->get_conflicts();
        $files     = $diag->get_integrity_errors();
        $hooks     = $diag->get_hook_issues();

        $issues = [];
        if (!empty($conflicts)) {
            $issues[] = sprintf(
                esc_html__('Conflicting SEO plugins: %s.', 'gm2-wordpress-suite'),
                esc_html(implode(', ', $conflicts))
            );
        }
        if (!empty($files)) {
            $issues[] = sprintf(
                esc_html__('Missing plugin files: %s.', 'gm2-wordpress-suite'),
                esc_html(implode(', ', $files))
            );
        }
        if (!empty($hooks)) {
            $issues[] = sprintf(
                esc_html__('Theme hooks removed: %s.', 'gm2-wordpress-suite'),
                esc_html(implode(', ', $hooks))
            );
        }

        if (empty($issues)) {
            return [
                'label'       => __('Gm2 Suite Diagnostics', 'gm2-wordpress-suite'),
                'status'      => 'good',
                'badge'       => [ 'label' => __('Performance', 'gm2-wordpress-suite'), 'color' => 'blue' ],
                'description' => '<p>' . esc_html__('No issues detected with Gm2 SEO output.', 'gm2-wordpress-suite') . '</p>',
                'actions'     => '',
                'test'        => 'gm2_suite_diagnostics',
            ];
        }

        $description = '<p>' . implode('<br />', $issues) . '</p>';
        $actions     = '<p>' . esc_html__('Please resolve these issues to ensure all SEO features work as expected.', 'gm2-wordpress-suite') . '</p>';

        return [
            'label'       => __('Gm2 Suite Diagnostics', 'gm2-wordpress-suite'),
            'status'      => 'recommended',
            'badge'       => [ 'label' => __('Performance', 'gm2-wordpress-suite'), 'color' => 'orange' ],
            'description' => $description,
            'actions'     => $actions,
            'test'        => 'gm2_suite_diagnostics',
        ];
    }
}
