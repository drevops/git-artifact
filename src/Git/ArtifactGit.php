<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Git;

use CzProject\GitPhp\Git;

/**
 * Artifact git class.
 */
class ArtifactGit extends Git {

  /**
   * Open directory.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function open($directory): ArtifactGitRepository {
    return new ArtifactGitRepository($directory, $this->runner);
  }

}
