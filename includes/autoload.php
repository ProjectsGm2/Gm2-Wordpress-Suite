<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'Gm2\\') !== 0) {
        return;
    }

    if (function_exists('gm2_substr')) {
        $name = gm2_substr($class, 4); // class name without namespace
    } elseif (function_exists('mb_substr')) {
        $name = mb_substr($class, 4, null, 'UTF-8');
    } else {
        $name = substr($class, 4);
    }
    foreach (['includes', 'admin', 'public'] as $dir) {
        $file = GM2_PLUGIN_DIR . $dir . '/' . $name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
