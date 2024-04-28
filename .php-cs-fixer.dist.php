<?php

require_once __DIR__ . '/vendor/autoload.php';

$config = new Nadystyle\Config;

$config->getFinder()
    ->in(__DIR__ . '/src');

$config->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');

return $config;
