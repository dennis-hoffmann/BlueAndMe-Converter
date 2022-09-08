#!/usr/bin/env php
<?php

ini_set("error_reporting", E_ALL & ~E_DEPRECATED);

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new App\ConvertCommand());

$application->run();
