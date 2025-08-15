<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Evaluate condition groups for a field or argument.
 *
 * @param array $item    Field or arg array possibly containing `conditions` or `conditional` keys.
 * @param int   $post_id Post ID context for meta lookup.
 * @return bool True if conditions pass or none are defined.
 */
function gm2_evaluate_conditions($item, $post_id = 0) {
    if (!is_array($item)) {
        return true;
    }

    // Build groups from modern `conditions` or legacy `conditional`.
    $groups = [];
    if (!empty($item['conditions']) && is_array($item['conditions'])) {
        $groups = $item['conditions'];
    } elseif (!empty($item['conditional']['field']) && isset($item['conditional']['value'])) {
        $groups = [
            [
                'relation'   => 'AND',
                'conditions' => [
                    [
                        'relation' => 'AND',
                        'target'   => $item['conditional']['field'],
                        'operator' => '=',
                        'value'    => (string) $item['conditional']['value'],
                    ],
                ],
            ],
        ];
    }

    if (empty($groups)) {
        return true;
    }

    $result = null;
    foreach ($groups as $group) {
        $group_res = null;
        foreach (($group['conditions'] ?? []) as $cond) {
            $target = $cond['target'] ?? '';
            if ($target === '') {
                continue;
            }
            // Determine the current value from request or post meta.
            if (isset($_REQUEST[$target])) {
                $current = $_REQUEST[$target];
                if (is_array($current)) {
                    $current = reset($current);
                }
            } else {
                $current = ($post_id) ? get_post_meta($post_id, $target, true) : '';
            }
            $current = (string) $current;
            $expected = (string) ($cond['value'] ?? '');
            $ok = false;
            switch ($cond['operator'] ?? '=') {
                case '!=':
                    $ok = $current !== $expected;
                    break;
                case '>':
                    $ok = floatval($current) > floatval($expected);
                    break;
                case '<':
                    $ok = floatval($current) < floatval($expected);
                    break;
                case 'contains':
                    $ok = strpos($current, $expected) !== false;
                    break;
                default:
                    $ok = $current === $expected;
                    break;
            }
            if ($group_res === null) {
                $group_res = $ok;
            } else {
                $rel = strtoupper($cond['relation'] ?? 'AND');
                $group_res = ($rel === 'OR') ? ($group_res || $ok) : ($group_res && $ok);
            }
        }
        if ($group_res === null) {
            $group_res = false;
        }
        if ($result === null) {
            $result = $group_res;
        } else {
            $rel = strtoupper($group['relation'] ?? 'AND');
            $result = ($rel === 'OR') ? ($result || $group_res) : ($result && $group_res);
        }
    }

    return ($result === null) ? true : $result;
}
