<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Quantity_Discount_Manager {

    private $option_name = 'gm2_quantity_discount_groups';

    public function get_groups() {
        $groups = get_option($this->option_name, []);
        if (!is_array($groups)) {
            $groups = [];
        }
        return $groups;
    }

    public function get_group($id) {
        $groups = $this->get_groups();
        foreach ($groups as $group) {
            if ($group['id'] == $id) {
                return $group;
            }
        }
        return false;
    }

    public function add_group($data) {
        $groups = $this->get_groups();

        if (function_exists('wp_generate_uuid4')) {
            $data['id'] = wp_generate_uuid4();
        } else {
            $data['id'] = uniqid('', true);
        }

        $groups[] = $data;
        update_option($this->option_name, $groups);

        return $data['id'];
    }

    public function update_group($id, $data) {
        $groups = $this->get_groups();
        foreach ($groups as &$group) {
            if ($group['id'] == $id) {
                $data['id'] = $id;
                $group = array_merge($group, $data);
                break;
            }
        }
        update_option($this->option_name, $groups);
    }

    public function delete_group($id) {
        $groups = $this->get_groups();
        $groups = array_filter($groups, function($g) use ($id) {
            return $g['id'] != $id;
        });
        update_option($this->option_name, array_values($groups));
    }
}
