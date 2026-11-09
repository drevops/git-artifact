<?php

/**
 * @file
 * Main entry point for the application.
 */

declare(strict_types=1);

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use Symfony\Component\Console\Application;

// @codeCoverageIgnoreStart
$application = new Application('Git Artifact', '@git-artifact-version@');

$command = new ArtifactCommand();
$application->add($command);
$application->setDefaultCommand((string) $command->getName(), TRUE);

$application->run();
// @codeCoverageIgnoreEnd
