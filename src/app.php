<?php

/**
 * @file
 * Main entry point for the application.
 */

use DrevOps\Robo\Commands\ArtifactCommand;
use Symfony\Component\Console\Application;

$application = new Application();
if (isset($runner) && $runner instanceof \Robo\Runner) {
  $command = new ArtifactCommand($runner);
  $application->add($command);
  $application->setDefaultCommand((string) $command->getName(), true);
}
$application->run();
