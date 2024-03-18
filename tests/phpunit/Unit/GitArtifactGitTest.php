<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\GitArtifactGit;
use DrevOps\GitArtifact\GitArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test git artifact git.
 */
#[CoversClass(GitArtifactGit::class)]
class GitArtifactGitTest extends AbstractUnitTestCase {

  /**
   * Test open directory.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testOpen(): void {
    $repo = $this->git->open($this->src);
    $this->assertEquals(GitArtifactGitRepository::class, $repo::class);
  }

}
