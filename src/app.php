<?php

/**
 * @file
 * Main entry point for the application.
 */
use Robo\Runner;
use DrevOps\GitArtifact\Commands\ArtifactCommand;
use Symfony\Component\Console\Application;

$application = new Application();
if (isset($runner) && $runner instanceof Runner) {
    $command = new ArtifactCommand($runner);
    $application->add($command);
    $application->setDefaultCommand((string) $command->getName(), true);
}
$application->run();
