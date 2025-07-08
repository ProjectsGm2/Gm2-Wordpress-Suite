<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'Gm2\\') !== 0) {
        return;
    }

    $name = substr($class, 4); // class name without namespace
    foreach (['includes', 'admin', 'public'] as $dir) {
        $file = GM2_PLUGIN_DIR . $dir . '/' . $name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
