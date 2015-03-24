<?php
ini_set('display_errors', 1);
if (defined("AUTOLOAD_PATH")) {
    if (is_file(__DIR__ . '/../' .AUTOLOAD_PATH)) {
        $loader = include_once __DIR__ . '/../' . AUTOLOAD_PATH;
    } else {
        echo "Cannot load autoload file located at ".AUTOLOAD_PATH;
        exit();
    }
}
