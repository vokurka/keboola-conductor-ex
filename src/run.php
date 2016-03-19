<?php

use Symfony\Component\Yaml\Yaml;

require_once(dirname(__FILE__) . "/../vendor/autoload.php");

require_once "Keboola/Conductor/Conductor.php";

ini_set('memory_limit','512M');

$arguments = getopt("d::", array("data::"));
if (!isset($arguments["data"])) {
    print "Data folder not set.";
    exit(1);
}

$config = Yaml::parse(file_get_contents($arguments["data"] . "/config.yml"));

try {
    $conductor = new Conductor(
        $config['parameters'],
        $arguments["data"] . "/out/tables/"
    );

    $conductor->run();
} catch (Exception $e) {
    print $e->getMessage();
    exit(1);
}

exit(0);
