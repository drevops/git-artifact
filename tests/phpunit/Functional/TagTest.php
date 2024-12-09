<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use CzProject\GitPhp\Git;
use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class TagTest extends AbstractFunctionalTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->mode = ArtifactCommand::MODE_FORCE_PUSH;
    parent::setUp();
  }

  public function testDetachedTag(): void {
    $this->gitCreateFixtureCommits(2);
    $this->gitAddTag($this->src, 'tag1');
    $this->gitCheckout($this->src, 'tag1');

    $repo = (new Git())->open($this->src);
    $branches = $repo->getBranches();

    $output = $this->assertArtifactCommandSuccess();
    $this->assertStringContainsString('Mode:                  ' . ArtifactCommand::MODE_FORCE_PUSH, $output);
    $this->assertStringContainsString('Will push:             Yes', $output);

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    $this->assertEquals($branches, $repo->getBranches(), 'Cleanup has correctly returned to the previous branch.');
  }

}
