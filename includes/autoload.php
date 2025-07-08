<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'Gm2\\') !== 0) {
        return;
    }
    $name = substr($class, 4);                  // strip namespace prefix
    $name = str_replace('_', DIRECTORY_SEPARATOR, $name);
    $file = GM2_PLUGIN_DIR . $name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
