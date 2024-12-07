<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class BranchModeTest extends AbstractFunctionalTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->mode = 'branch';
    parent::setUp();
  }

  public function testBuild(): void {
    $this->gitCreateFixtureCommits(2);

    $output = $this->assertCommandSuccess();
    $this->assertStringContainsString('WARNING! Provided branch name does not have a token', $output);
    $this->assertStringContainsString('Mode:                  branch', $output);
    $this->assertStringContainsString('Will push:             Yes', $output);

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
  }

  public function testBuildMoreCommitsSameBranch(): void {
    $this->gitCreateFixtureCommits(2);

    $this->assertCommandSuccess();

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

    $this->gitCreateFixtureCommits(3, 2);
    $this->assertCommandFailure();

    // Make sure that broken artifact was not pushed.
    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
  }

  public function testBuildMoreCommits(): void {
    $this->gitCreateFixtureCommits(2);

    $this->now = time() - rand(1, 10 * 60);
    $branch1 = 'testbranch-' . date('Y-m-d_H-i-s', $this->now);
    $output = $this->assertCommandSuccess(['--branch' => 'testbranch-[timestamp:Y-m-d_H-i-s]'], $branch1);
    $this->assertStringContainsString('Remote branch:         ' . $branch1, $output);
    $this->assertStringNotContainsString('WARNING! Provided branch name does not have a token', $output);

    $this->gitAssertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);

    $this->gitCreateFixtureCommits(3, 2);

    $this->now = time() - rand(1, 10 * 60);
    $branch2 = 'testbranch-' . date('Y-m-d_H-i-s', $this->now);
    $output = $this->assertCommandSuccess(['--branch' => 'testbranch-[timestamp:Y-m-d_H-i-s]'], $branch2);
    $this->assertStringContainsString('Remote branch:         ' . $branch2, $output);
    $this->gitAssertFixtureCommits(5, $this->dst, $branch2, ['Deployment commit']);

    // Also, check that no changes were done to branch1.
    $this->gitAssertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
  }

  public function testCleanupAfterSuccess(): void {
    $this->gitCreateFixtureCommits(2);

    $this->assertCommandSuccess();
    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

    $this->gitAssertCurrentBranch($this->src, $this->currentBranch);
    $this->gitAssertRemoteNotExists($this->src, $this->remoteName);
  }

  public function testCleanupAfterFailure(): void {
    $this->gitCreateFixtureCommits(2);

    $this->assertCommandSuccess();
    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

    $this->gitCreateFixtureCommits(3, 2);
    // Trigger erroneous build by pushing to the same branch.
    $this->assertCommandFailure();

    $this->gitAssertCurrentBranch($this->src, $this->currentBranch);
    $this->gitAssertRemoteNotExists($this->src, $this->remoteName);
  }

  public function testGitignore(): void {
    $this->fixtureCreateFile($this->src, '.gitignore', 'f3');
    $this->gitCreateFixtureCommits(2);
    $this->fixtureCreateFile($this->src, 'f3');

    $this->now = time() - rand(1, 10 * 60);
    $branch1 = 'testbranch-' . date('Y-m-d_H-i-s', $this->now);
    $this->assertCommandSuccess(['--branch' => 'testbranch-[timestamp:Y-m-d_H-i-s]'], $branch1);

    $this->gitAssertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
    $this->assertFileDoesNotExist($this->dst . DIRECTORY_SEPARATOR . 'f3');

    // Now, remove the .gitignore and push again.
    $this->fixtureRemoveFile($this->src, '.gitignore');
    $this->gitCommitAll($this->src, 'Commit number 3');
    $this->now = time() - rand(1, 10 * 60);
    $branch2 = 'testbranch-' . date('Y-m-d_H-i-s', $this->now);
    $this->assertCommandSuccess(['--branch' => 'testbranch-[timestamp:Y-m-d_H-i-s]'], $branch2);

    $this->gitAssertFixtureCommits(3, $this->dst, $branch2, ['Deployment commit']);

    // Assert that branch from previous deployment was not affected.
    $this->gitAssertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
    $this->assertFileDoesNotExist($this->dst . DIRECTORY_SEPARATOR . 'f3');
  }

  public function testGitignoreCustom(): void {
    $this->fixtureCreateFile($this->src, 'mygitignore', 'f3');
    $this->gitCreateFixtureCommits(2);
    $this->fixtureCreateFile($this->src, 'f3');

    $this->now = time() - rand(1, 10 * 60);
    $branch1 = 'testbranch-' . date('Y-m-d_H-i-s', $this->now);
    $this->assertCommandSuccess([
      '--branch' => 'testbranch-[timestamp:Y-m-d_H-i-s]',
      '--gitignore' => $this->src . DIRECTORY_SEPARATOR . 'mygitignore',
    ], $branch1);

    $this->gitAssertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
    $this->assertFileDoesNotExist($this->dst . DIRECTORY_SEPARATOR . 'f3');

    // Now, remove the .gitignore and push again.
    $this->fixtureCreateFile($this->src, 'f3');
    $this->fixtureRemoveFile($this->src, 'mygitignore');
    $this->gitCommitAll($this->src, 'Commit number 3');
    $this->now = time() - rand(1, 10 * 60);
    $branch2 = 'testbranch-' . date('Y-m-d_H-i-s', $this->now);
    $this->assertCommandSuccess(['--branch' => 'testbranch-[timestamp:Y-m-d_H-i-s]'], $branch2);

    $this->gitAssertFixtureCommits(3, $this->dst, $branch2, ['Deployment commit']);

    // Assert that branch from previous deployment was not affected.
    $this->gitAssertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
    $this->assertFileDoesNotExist($this->dst . DIRECTORY_SEPARATOR . 'f3');
  }

}
