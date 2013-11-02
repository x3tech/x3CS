<?php

require_once(dirname(dirname(__FILE__)) . "/config.php");
require_once(dirname(dirname(__FILE__)) . "/vendor/x3/checkin_system/x3/CheckinSystem/System/App.php");
require_once(dirname(dirname(__FILE__)) . "/vendor/x3/checkin_system/x3/CheckinSystem/System/Controller.php");

setup($app);
$app->run();
