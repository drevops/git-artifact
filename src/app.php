<?php

/**
 * @file
 * Main entry point for the application.
 */

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\GitArtifactGit;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

$application = new Application();

$git = new GitArtifactGit();
$filesystem = new Filesystem();

$command = new ArtifactCommand($git, $filesystem);
$application->add($command);
$application->setDefaultCommand((string) $command->getName(), TRUE);

$application->run();
