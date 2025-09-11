<?php

include_once "../vendor/autoload.php";

$diContainer = new CandlewaxGames\Bootstrap\DIContainer();
$bootstrapper = $diContainer->getDI()->get(CandlewaxGames\Bootstrap\Bootstrapper::class);
$bootstrapper->init();
