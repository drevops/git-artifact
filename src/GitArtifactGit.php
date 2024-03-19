<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact;

use CzProject\GitPhp\Git;

/**
 * Git Artifact git class.
 */
class GitArtifactGit extends Git {

  /**
   * Open directory.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function open($directory): GitArtifactGitRepository {
    return new GitArtifactGitRepository($directory, $this->runner);
  }

}
