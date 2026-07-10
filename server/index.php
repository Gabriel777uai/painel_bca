<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/php/AppBuilder.php';

$app = AppBuilder::createApp();
$app->run();
