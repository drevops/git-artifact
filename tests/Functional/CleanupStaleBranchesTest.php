<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class CleanupStaleBranchesTest extends FunctionalTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->mode = ArtifactCommand::MODE_BRANCH;
    parent::setUp();
  }

  public function testPrunesStaleBranches(): void {
    $this->gitCommitFileWithDate($this->dst, 'init', $this->now - 86400, 'Initial');
    $this->gitCreateBranchWithCommitDate($this->dst, 'deployment/old1', $this->now - 10 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'deployment/old2', $this->now - 4 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'deployment/fresh', $this->now - 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'feature/keep', $this->now - 10 * 86400);
    $this->gitCheckout($this->dst, $this->currentBranch);

    $this->gitCreateFixtureCommits(2);

    $output = $this->assertArtifactCommandSuccess([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => 'deployment/*',
      '--cleanup-age' => '3',
    ]);

    $this->assertStringContainsString('Cleanup stale:         Yes (pattern "deployment/*", older than 3 days)', $output);
    $this->assertStringContainsString('Deleted stale branch "deployment/old1"', $output);
    $this->assertStringContainsString('Deleted stale branch "deployment/old2"', $output);

    $this->gitAssertBranchesNotExist($this->dst, ['deployment/old1', 'deployment/old2']);
    $this->gitAssertBranchesExist($this->dst, ['deployment/fresh', 'feature/keep', 'testbranch', $this->currentBranch]);
  }

  public function testPrunesStaleBranchesCommaSeparatedGlobs(): void {
    $this->gitCommitFileWithDate($this->dst, 'init', $this->now - 86400, 'Initial');
    $this->gitCreateBranchWithCommitDate($this->dst, 'feature/old', $this->now - 10 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'bugfix/old', $this->now - 10 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'release/keep', $this->now - 10 * 86400);
    $this->gitCheckout($this->dst, $this->currentBranch);

    $this->gitCreateFixtureCommits(2);

    $output = $this->assertArtifactCommandSuccess([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => 'feature/*,,bugfix/*',
      '--cleanup-age' => '3',
    ]);

    $this->assertStringContainsString('Cleanup stale:         Yes (patterns "feature/*", "bugfix/*", older than 3 days)', $output);
    $this->assertStringContainsString('Deleted stale branch "bugfix/old"', $output);
    $this->assertStringContainsString('Deleted stale branch "feature/old"', $output);

    $this->gitAssertBranchesNotExist($this->dst, ['feature/old', 'bugfix/old']);
    $this->gitAssertBranchesExist($this->dst, ['release/keep', 'testbranch', $this->currentBranch]);
  }

  public function testPrunesStaleBranchesMultiplePatterns(): void {
    $this->gitCommitFileWithDate($this->dst, 'init', $this->now - 86400, 'Initial');
    $this->gitCreateBranchWithCommitDate($this->dst, 'feature/old', $this->now - 10 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'bugfix/old', $this->now - 10 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'release/keep', $this->now - 10 * 86400);
    $this->gitCheckout($this->dst, $this->currentBranch);

    $this->gitCreateFixtureCommits(2);

    $output = $this->assertArtifactCommandSuccess([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => ['feature/*', 'bugfix/*'],
      '--cleanup-age' => '3',
    ]);

    $this->assertStringContainsString('Cleanup stale:         Yes (patterns "feature/*", "bugfix/*", older than 3 days)', $output);
    $this->assertStringContainsString('Deleted stale branch "bugfix/old"', $output);
    $this->assertStringContainsString('Deleted stale branch "feature/old"', $output);

    $this->gitAssertBranchesNotExist($this->dst, ['feature/old', 'bugfix/old']);
    $this->gitAssertBranchesExist($this->dst, ['release/keep', 'testbranch', $this->currentBranch]);
  }

  public function testPrunesStaleBranchesRegex(): void {
    $this->gitCommitFileWithDate($this->dst, 'init', $this->now - 86400, 'Initial');
    $this->gitCreateBranchWithCommitDate($this->dst, 'feature/single', $this->now - 10 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'feature/nested/deep', $this->now - 10 * 86400);
    $this->gitCreateBranchWithCommitDate($this->dst, 'bugfix/one', $this->now - 10 * 86400);
    $this->gitCheckout($this->dst, $this->currentBranch);

    $this->gitCreateFixtureCommits(2);

    $output = $this->assertArtifactCommandSuccess([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => '/^feature\/[^\/]+$/',
      '--cleanup-age' => '3',
    ]);

    $this->assertStringContainsString('Cleanup stale:         Yes (pattern "/^feature\/[^\/]+$/", older than 3 days)', $output);
    $this->assertStringContainsString('Deleted stale branch "feature/single"', $output);

    $this->gitAssertBranchesNotExist($this->dst, ['feature/single']);
    // The regex allows a single path segment only, so the nested branch and the
    // bugfix branch are preserved even though both are stale.
    $this->gitAssertBranchesExist($this->dst, ['feature/nested/deep', 'bugfix/one', 'testbranch', $this->currentBranch]);
  }

  public function testDryRunDoesNotPrune(): void {
    $this->gitCommitFileWithDate($this->dst, 'init', $this->now - 86400, 'Initial');
    $this->gitCreateBranchWithCommitDate($this->dst, 'deployment/old', $this->now - 10 * 86400);
    $this->gitCheckout($this->dst, $this->currentBranch);

    $this->gitCreateFixtureCommits(2);

    $output = $this->runArtifactCommand([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => 'deployment/*',
      '--cleanup-age' => '3',
      '--dry-run' => TRUE,
      '--branch' => 'testbranch',
    ]);

    $this->assertStringContainsString('Would delete stale branch "deployment/old"', $output);
    $this->assertStringNotContainsString('Deleted stale branch', $output);
    $this->gitAssertBranchesExist($this->dst, ['deployment/old']);
  }

  public function testProtectsDefaultAndPushedBranches(): void {
    $this->gitCommitFileWithDate($this->dst, 'init', $this->now - 100 * 86400, 'Initial');
    $this->gitCreateBranchWithCommitDate($this->dst, 'deployment/old', $this->now - 100 * 86400);
    $this->gitCheckout($this->dst, $this->currentBranch);

    $this->gitCreateFixtureCommits(2);

    $output = $this->assertArtifactCommandSuccess([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => '*',
      '--cleanup-age' => '3',
    ]);

    $this->assertStringContainsString('Deleted stale branch "deployment/old"', $output);
    $this->gitAssertBranchesNotExist($this->dst, ['deployment/old']);
    // The just-pushed branch (fresh) and the remote default branch (stale) both
    // match the "*" pattern but are always preserved regardless of their age.
    $this->gitAssertBranchesExist($this->dst, ['testbranch', $this->currentBranch]);
  }

  public function testNoStaleBranches(): void {
    $this->gitCommitFileWithDate($this->dst, 'init', $this->now - 86400, 'Initial');
    $this->gitCreateBranchWithCommitDate($this->dst, 'deployment/fresh', $this->now - 86400);
    $this->gitCheckout($this->dst, $this->currentBranch);

    $this->gitCreateFixtureCommits(2);

    $output = $this->assertArtifactCommandSuccess([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => 'deployment/*',
      '--cleanup-age' => '3',
    ]);

    $this->assertStringContainsString('No stale branches to clean up.', $output);
    $this->gitAssertBranchesExist($this->dst, ['deployment/fresh']);
  }

  public function testRemoteDefaultBranchUnknownRemoteReturnsNull(): void {
    $repo = new ArtifactGitRepository($this->src);

    $this->assertNull($repo->getRemoteDefaultBranch('does-not-exist'));
  }

  public function testRequiresPattern(): void {
    $this->gitCreateFixtureCommits(2);

    $output = $this->runArtifactCommand([
      '--cleanup-stale' => TRUE,
      '--branch' => 'testbranch',
    ], TRUE);

    $this->assertStringContainsString('The --cleanup-pattern option is required when --cleanup-stale is set.', $output);
  }

  public function testRequiresNonEmptyPattern(): void {
    $this->gitCreateFixtureCommits(2);

    $output = $this->runArtifactCommand([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => ['  ', ''],
      '--branch' => 'testbranch',
    ], TRUE);

    $this->assertStringContainsString('The --cleanup-pattern option is required when --cleanup-stale is set.', $output);
  }

  public function testRejectsInvalidRegex(): void {
    $this->gitCreateFixtureCommits(2);

    $output = $this->runArtifactCommand([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => '/(/',
      '--branch' => 'testbranch',
    ], TRUE);

    $this->assertStringContainsString('The --cleanup-pattern value "/(/" is not a valid regular expression.', $output);
  }

  #[DataProvider('dataProviderRejectsInvalidAge')]
  public function testRejectsInvalidAge(string $age): void {
    $this->gitCreateFixtureCommits(2);

    $output = $this->runArtifactCommand([
      '--cleanup-stale' => TRUE,
      '--cleanup-pattern' => 'deployment/*',
      '--cleanup-age' => $age,
      '--branch' => 'testbranch',
    ], TRUE);

    $this->assertStringContainsString('The --cleanup-age option must be a positive integer number of days.', $output);
  }

  /**
   * @return array<string, array<string>>
   *   Test data.
   */
  public static function dataProviderRejectsInvalidAge(): array {
    return [
      'zero' => ['0'],
      'negative' => ['-1'],
      'non-numeric' => ['abc'],
      'fractional' => ['3.5'],
    ];
  }

}
