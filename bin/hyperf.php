<?php

require 'vendor/autoload.php';

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

$application = new \Symfony\Component\Console\Application();
$config = new \Rebuild\Config\ConfigFactory();
$config = $config();

$commands = $config->get('commands');
foreach ($commands as $command) {
    if ($command === \Rebuild\Command\StartCommand::class) {
        $application->add(new \Rebuild\Command\StartCommand($config));
    } else {
        $application->add(new $command);
    }
}
$application->run();