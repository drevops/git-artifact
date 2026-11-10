<?php

/**
 * @file
 * Main entry point for the application.
 */

declare(strict_types=1);

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use Symfony\Component\Console\Application;

// @codeCoverageIgnoreStart
// Box replaces the placeholder only in the PHAR; a source or Composer install
// still carries it, so report 'development' there.
$version = '@git-artifact-version@';
$application = new Application('Git Artifact', str_starts_with($version, '@') ? 'development' : $version);

$command = new ArtifactCommand();
$application->add($command);
$application->setDefaultCommand((string) $command->getName(), TRUE);

$application->run();
// @codeCoverageIgnoreEnd
