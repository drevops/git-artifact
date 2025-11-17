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

  /**
   * Test deployment skips gracefully when branch is missing (default).
   *
   * This simulates the scenario where a branch was deleted after PR merge.
   * Default behavior should skip deployment with exit code 0.
   */
  public function testMissingBranchDefaultBehavior(): void {
    $this->gitCreateFixtureCommits(1);

    // Create an orphaned commit (not on any branch).
    $repo = (new Git())->open($this->src);

    // Create orphaned branch.
    $repo->run('checkout', '--orphan', 'orphan-branch');

    // Create a commit on orphan branch.
    $this->fixtureCreateFile($this->src, 'f_orphan');
    $repo->addAllChanges();
    $repo->commit('Orphan commit');

    // Get the commit hash.
    $commits = $repo->execute(['rev-parse', 'HEAD']);
    $commit_hash = $commits[0];

    // Switch back to main branch and delete orphan branch.
    $repo->checkout($this->currentBranch);
    $repo->run('branch', '-D', 'orphan-branch');

    // Checkout the orphaned commit - now it's truly not on any branch.
    $repo->checkout($commit_hash);

    // Default behavior: should skip deployment with exit code 0.
    // Use explicit branch name (no tokens) to avoid token processing issues.
    $output = $this->runArtifactCommand([
      '--branch' => 'testbranch',
      '--dry-run' => TRUE,
    ]);

    $this->assertStringContainsString('Source branch not found. Deployment skipped.', $output);
    $this->assertStringContainsString('Commit: ' . $commit_hash, $output);
    $this->assertStringContainsString('Use --fail-on-missing-branch to fail deployment instead', $output);
    $this->assertStringNotContainsString('Processing failed with an error:', $output);
  }

  /**
   * Test deployment fails when branch is missing with flag.
   *
   * With --fail-on-missing-branch flag, deployment should fail.
   */
  public function testMissingBranchWithFlag(): void {
    $this->gitCreateFixtureCommits(1);

    // Create an orphaned commit (not on any branch).
    $repo = (new Git())->open($this->src);

    // Create orphaned branch.
    $repo->run('checkout', '--orphan', 'orphan-branch-2');

    // Create a commit on orphan branch.
    $this->fixtureCreateFile($this->src, 'f_orphan_2');
    $repo->addAllChanges();
    $repo->commit('Orphan commit 2');

    // Get the commit hash.
    $commits = $repo->execute(['rev-parse', 'HEAD']);
    $commit_hash = $commits[0];

    // Switch back to main branch and delete orphan branch.
    $repo->checkout($this->currentBranch);
    $repo->run('branch', '-D', 'orphan-branch-2');

    // Checkout the orphaned commit - now it's truly not on any branch.
    $repo->checkout($commit_hash);

    // With --fail-on-missing-branch, should fail.
    // Use explicit branch name (no tokens) to avoid token processing issues.
    $output = $this->runArtifactCommand([
      '--branch' => 'testbranch',
      '--fail-on-missing-branch' => TRUE,
      '--dry-run' => TRUE,
    ], TRUE);

    $this->assertStringContainsString('Processing failed with an error:', $output);
    $this->assertStringContainsString('Unable to determine source branch', $output);
  }

  /**
   * Test normal deployment when branch exists.
   *
   * Verifies that normal operation still works when branch is available.
   */
  public function testNormalDeploymentWithBranch(): void {
    $this->gitCreateFixtureCommits(1);

    // Normal deployment with existing branch should work.
    $output = $this->assertArtifactCommandSuccess();

    $this->assertStringContainsString('Pushed branch "testbranch" with commit message "Deployment commit"', $output);
    $this->assertStringContainsString('Deployment finished successfully.', $output);

    // Verify the branch exists in destination.
    $this->gitCheckout($this->dst, 'testbranch');
    $this->assertFilesExist($this->dst, 'f1');
  }

  /**
   * Test deployment works with tag in detached HEAD state.
   *
   * When checked out at a tag, getOriginalBranch() should validate that
   * the tag exists and allow deployment to proceed.
   */
  public function testDeploymentWithTagDetachedHead(): void {
    $this->gitCreateFixtureCommits(1);

    $repo = (new Git())->open($this->src);

    // Create a tag at the current commit.
    $repo->run('tag', 'v1.0.0');

    // Checkout the tag to enter detached HEAD state.
    $repo->checkout('v1.0.0');

    // Deployment should work because tag is a valid detachment source.
    $output = $this->assertArtifactCommandSuccess();

    $this->assertStringContainsString('Pushed branch "testbranch" with commit message "Deployment commit"', $output);
    $this->assertStringContainsString('Deployment finished successfully.', $output);
  }

}
