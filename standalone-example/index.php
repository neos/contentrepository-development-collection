<?php


require __DIR__ . '/vendor/autoload.php';

(new \Neos\StandaloneCrExample\Migrations())->run();
$example1 = new \Neos\StandaloneCrExample\Example1();
$example1->run();
