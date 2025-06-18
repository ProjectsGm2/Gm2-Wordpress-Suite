<?php
if (!defined('ABSPATH')) {
    exit;
}

class Gm2_Tariff_Manager {

    private $option_name = 'gm2_tariffs';

    public function get_tariffs() {
        $tariffs = get_option($this->option_name, []);
        if (!is_array($tariffs)) {
            $tariffs = [];
        }
        return $tariffs;
    }

    public function get_tariff($id) {
        $tariffs = $this->get_tariffs();
        foreach ($tariffs as $tariff) {
            if ($tariff['id'] == $id) {
                return $tariff;
            }
        }
        return false;
    }

    public function add_tariff($data) {
        $tariffs = $this->get_tariffs();
        $data['id'] = time();
        $tariffs[] = $data;
        update_option($this->option_name, $tariffs);
        return $data['id'];
    }

    public function update_tariff($id, $data) {
        $tariffs = $this->get_tariffs();
        foreach ($tariffs as &$tariff) {
            if ($tariff['id'] == $id) {
                $data['id'] = $id;
                $tariff = array_merge($tariff, $data);
                break;
            }
        }
        update_option($this->option_name, $tariffs);
    }

    public function delete_tariff($id) {
        $tariffs = $this->get_tariffs();
        $tariffs = array_filter($tariffs, function($t) use ($id) {
            return $t['id'] != $id;
        });
        update_option($this->option_name, array_values($tariffs));
    }
}
