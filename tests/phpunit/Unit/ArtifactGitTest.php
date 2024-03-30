<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Git\ArtifactGit;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test git artifact git.
 */
#[CoversClass(ArtifactGit::class)]
class ArtifactGitTest extends AbstractUnitTestCase {

  /**
   * Test open directory.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testOpen(): void {
    $repo = $this->git->open($this->src);
    $this->assertEquals(ArtifactGitRepository::class, $repo::class);
  }

}
