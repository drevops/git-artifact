<?php

/**
 * @file
 * Main entry point for the application.
 */

use GitWrapper\GitWrapper;
use DrevOps\GitArtifact\Commands\ArtifactCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem as FileSystem;

$application = new Application();
$gitWrapper = new GitWrapper();
$fileSystem = new FileSystem();
$command = new ArtifactCommand($gitWrapper, $fileSystem);
$application->add($command);
$application->setDefaultCommand((string) $command->getName(), true);
$application->run();
