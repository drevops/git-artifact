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

  /**
   * Init a git repo specific initial branch name.
   *
   * @param string $directory
   *   Directory.
   * @param string $branchName
   *   Branch name.
   *
   * @return \DrevOps\GitArtifact\GitArtifactGitRepository
   *   Git repo.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function initWithInitialBranch(string $directory, string $branchName): GitArtifactGitRepository {
    /** @var \DrevOps\GitArtifact\GitArtifactGitRepository $repo */
    $repo = $this->init($directory, ['-b' => $branchName]);

    return $repo;
  }

}
