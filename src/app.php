<?php

/**
 * @file
 * Main entry point for the application.
 */

declare(strict_types=1);

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$command = new ArtifactCommand();
$application->add($command);
$application->setDefaultCommand((string) $command->getName(), TRUE);

$application->run();
