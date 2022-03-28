<?php

require ('../vendor/autoload.php');

use NhnEdu\TeamTaskCollector\TaskCollector;

$collector = new TaskCollector('config.json');
$collector->run();
