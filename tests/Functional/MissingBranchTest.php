<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use CzProject\GitPhp\Git;
use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class MissingBranchTest extends FunctionalTestCase {

  public function testMissingBranchDefaultBehavior(): void {
    $this->gitCreateFixtureCommits(1);

    // Create an orphaned commit (not on any branch).
    $repo = (new Git())->open($this->src);

    $repo->run('checkout', '--orphan', 'orphan-branch');

    $this->fixtureCreateFile($this->src, 'f_orphan');
    $repo->addAllChanges();
    $repo->commit('Orphan commit');

    $commits = $repo->execute(['rev-parse', 'HEAD']);
    $commit_hash = $commits[0];

    $repo->checkout($this->currentBranch);
    $repo->run('branch', '-D', 'orphan-branch');

    // The orphan branch is deleted, so the checked-out commit is on no branch.
    $repo->checkout($commit_hash);

    $output = $this->runArtifactCommand([
      '--branch' => 'testbranch',
      '--dry-run' => TRUE,
    ]);

    $this->assertStringContainsString('Source branch not found. Artifact packaging skipped.', $output);
    $this->assertStringContainsString('Commit: ' . $commit_hash, $output);
    $this->assertStringContainsString('Use --fail-on-missing-branch to fail artifact packaging instead.', $output);
    $this->assertStringNotContainsString('Processing failed with an error:', $output);
  }

  public function testMissingBranchWithFlag(): void {
    $this->gitCreateFixtureCommits(1);

    // Create an orphaned commit (not on any branch).
    $repo = (new Git())->open($this->src);

    $repo->run('checkout', '--orphan', 'orphan-branch-2');

    $this->fixtureCreateFile($this->src, 'f_orphan_2');
    $repo->addAllChanges();
    $repo->commit('Orphan commit 2');

    $commits = $repo->execute(['rev-parse', 'HEAD']);
    $commit_hash = $commits[0];

    $repo->checkout($this->currentBranch);
    $repo->run('branch', '-D', 'orphan-branch-2');

    // The orphan branch is deleted, so the checked-out commit is on no branch.
    $repo->checkout($commit_hash);

    $output = $this->runArtifactCommand([
      '--branch' => 'testbranch',
      '--fail-on-missing-branch' => TRUE,
      '--dry-run' => TRUE,
    ], TRUE);

    $this->assertStringContainsString('Processing failed with an error:', $output);
    $this->assertStringContainsString('Unable to determine source branch. Artifact packaging failed. Unable to determine a detachment source.', $output);
  }

  public function testPackageWithBranch(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->assertArtifactCommandSuccess();

    $this->assertStringContainsString('Pushed branch "testbranch" with commit message "Deployment commit".', $output);
    $this->assertStringContainsString('Artifact packaged successfully.', $output);

    $this->gitCheckout($this->dst, 'testbranch');
    $this->assertFilesExist($this->dst, 'f1');
  }

  public function testPackageWithTagDetachedHead(): void {
    $this->gitCreateFixtureCommits(1);

    $this->gitAddTag($this->src, 'v1.0.0');

    $this->gitCheckout($this->src, 'v1.0.0');

    // A tag is a valid detachment source, so artifact packaging succeeds.
    $output = $this->assertArtifactCommandSuccess();

    $this->assertStringContainsString('Pushed branch "testbranch" with commit message "Deployment commit".', $output);
    $this->assertStringContainsString('Artifact packaged successfully.', $output);
  }

}
