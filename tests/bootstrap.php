<?php

error_reporting(E_ALL | E_STRICT);

date_default_timezone_set('UTC');

if (!file_exists(dirname(__DIR__) . '/composer.lock')) {
    die("Dependencies must be installed using composer:\n\nphp composer.phar install --dev\n\n"
        . "See http://getcomposer.org for help with installing composer\n");
}

// Include the composer autoloader
$autoloader = require_once dirname(__DIR__) . '/vendor/autoload.php';