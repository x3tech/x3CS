<?php

define('ROOT_DIR', dirname(dirname(__FILE__)));
require_once(ROOT_DIR . "/vendor/x3/checkin_system/x3/CheckinSystem/System/App.php");

$app->mount('/', new x3\CheckinSystem\CheckinRoutesProvider());
$app->run();
