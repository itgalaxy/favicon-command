<?php

if (!class_exists('WP_CLI')) {
    return;
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';

if (file_exists($autoload)) {
    include_once $autoload;
}

WP_CLI::add_command('favicon', 'Favicon_Command');
