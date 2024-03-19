<?php

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\GitArtifactGit;
use DrevOps\GitArtifact\Tests\AbstractTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class AbstractUnitTestCase.
 */
abstract class AbstractUnitTestCase extends AbstractTestCase {

  /**
   * Artifact command.
   */
  protected ArtifactCommand $command;

  protected function setUp(): void {
    parent::setUp();

    $fileSystem = new Filesystem();
    $gitWrapper = new GitArtifactGit();
    $this->command = new ArtifactCommand($gitWrapper, $fileSystem);
    $this->callProtectedMethod($this->command, 'fsSetRootDir', [$this->fixtureDir]);
  }

}
