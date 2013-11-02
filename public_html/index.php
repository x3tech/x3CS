<?php

define('ROOT_DIR', dirname(dirname(__FILE__)));
require_once(ROOT_DIR . "/vendor/autoload.php");

$app = new Silex\Application();
$app->register(new x3\CheckinSystem\CheckinServiceProvider());
$app->mount('/', new x3\CheckinSystem\CheckinRoutesProvider());
$app->run();
