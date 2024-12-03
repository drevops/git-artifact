<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Tests\AbstractTestCase;

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

    $this->command = new ArtifactCommand();
    $this->callProtectedMethod($this->command, 'fsSetRootDir', [$this->fixtureDir]);
  }

}
