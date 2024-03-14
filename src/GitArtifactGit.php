<?php

namespace DrevOps\GitArtifact;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;

/**
 *
 */
class GitArtifactGit extends Git {

  /**
   *
   * @throws GitException
   */
  public function open($directory): GitArtifactGitRepository {
    return new GitArtifactGitRepository($directory, $this->runner);
  }

}
