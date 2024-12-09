<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class ForcePushTest.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class ForcePushModeTest extends AbstractFunctionalTestCase {

  protected function setUp(): void {
    $this->mode = ArtifactCommand::MODE_FORCE_PUSH;
    parent::setUp();
  }

  public function testBuild(): void {
    $this->gitCreateFixtureCommits(2);

    $output = $this->assertArtifactCommandSuccess();
    $this->assertStringContainsString('Mode:                  ' . ArtifactCommand::MODE_FORCE_PUSH, $output);
    $this->assertStringContainsString('Will push:             Yes', $output);

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
  }

  public function testBuildMoreCommits(): void {
    $this->gitCreateFixtureCommits(2);

    $this->assertArtifactCommandSuccess();

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

    $this->gitCreateFixtureCommits(3, 2);
    $this->assertArtifactCommandSuccess();

    $this->gitAssertFixtureCommits(5, $this->dst, 'testbranch', ['Deployment commit']);
  }

  public function testIdempotence(): void {
    $this->gitCreateFixtureCommits(2);

    $this->assertArtifactCommandSuccess();
    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

    $this->assertArtifactCommandSuccess();
    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
  }

  public function testSubRepos(): void {
    $this->gitCreateFixtureCommits(2);

    $this->fixtureCreateFile($this->src, 'c');
    $this->gitCommitAll($this->src, 'Commit number 3');

    $this->gitInitRepo($this->src . DIRECTORY_SEPARATOR . 'r1/');
    $this->fixtureCreateFile($this->src, 'r1/c');

    $this->gitInitRepo($this->src . DIRECTORY_SEPARATOR . 'r2/r21');
    $this->fixtureCreateFile($this->src, 'r2/r21/c');

    $this->gitInitRepo($this->src . DIRECTORY_SEPARATOR . 'r3/r31/r311');
    $this->fixtureCreateFile($this->src, 'r3/r31/r311/c');

    $this->assertFilesExist($this->src, ['r1/c']);
    $this->assertFilesNotExist($this->src, ['r1/.git/index']);
    $this->assertFilesNotExist($this->src, ['r2/r21.git/index']);
    $this->assertFilesNotExist($this->src, ['r3/r31/r311/.git/index']);

    $output = $this->assertArtifactCommandSuccess(['-vvv' => TRUE]);
    $this->assertStringContainsString(sprintf('Removing sub-repository "%s"', $this->fsGetAbsolutePath($this->src . DIRECTORY_SEPARATOR . 'r1/.git')), $output);
    $this->assertStringContainsString(sprintf('Removing sub-repository "%s"', $this->fsGetAbsolutePath($this->src . DIRECTORY_SEPARATOR . 'r2/r21/.git')), $output);
    $this->assertStringContainsString(sprintf('Removing sub-repository "%s"', $this->fsGetAbsolutePath($this->src . DIRECTORY_SEPARATOR . 'r3/r31/r311/.git')), $output);
    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Commit number 3', 'Deployment commit']);

    $this->assertFilesExist($this->dst, ['r1/c']);
    $this->assertFilesExist($this->dst, ['r2/r21/c']);
    $this->assertFilesExist($this->dst, ['r3/r31/r311/c']);
    $this->assertFilesNotExist($this->dst, ['r1/.git/index']);
    $this->assertFilesNotExist($this->dst, ['r1/.git']);
    $this->assertFilesNotExist($this->dst, ['r2/r21/.git/index']);
    $this->assertFilesNotExist($this->dst, ['r2/r21/.git']);
    $this->assertFilesNotExist($this->dst, ['r3/r31/311/.git/index']);
    $this->assertFilesNotExist($this->dst, ['r3/r31/311/.git']);
  }

  public function testCleanupAfterSuccess(): void {
    $this->gitCreateFixtureCommits(2);

    $this->assertArtifactCommandSuccess();
    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

    $this->gitAssertCurrentBranch($this->src, $this->currentBranch);
    $this->gitAssertRemoteNotExists($this->src, $this->remoteName);
  }

  public function testCleanupAfterFailure(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->assertArtifactCommandFailure(['--branch' => '*invalid']);

    $this->assertStringContainsString('Incorrect value "*invalid" specified for git remote branch', $output);
    $this->gitAssertCurrentBranch($this->src, $this->currentBranch);
    $this->gitAssertRemoteNotExists($this->src, $this->remoteName);
  }

  public function testGitignore(): void {
    $this->fixtureCreateFile($this->src, '.gitignore', 'f3');
    $this->gitCreateFixtureCommits(2);
    $this->fixtureCreateFile($this->src, 'f3');

    $this->assertArtifactCommandSuccess();

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    $this->assertFilesNotExist($this->dst, 'f3');

    // Now, remove the .gitignore and push again.
    $this->fixtureRemoveFile($this->src, '.gitignore');
    $this->gitCommitAll($this->src, 'Commit number 3');
    $this->assertArtifactCommandSuccess();
    $this->gitAssertFixtureCommits(3, $this->dst, 'testbranch', ['Deployment commit']);
  }

  public function testGitignoreCustom(): void {
    $this->gitCreateFixtureCommits(2);
    $this->fixtureCreateFile($this->src, 'uic');
    $this->fixtureCreateFile($this->src, 'uc');

    $this->fixtureCreateFile($this->src, 'mygitignore', 'uic');

    $this->assertArtifactCommandSuccess(['--gitignore' => $this->src . DIRECTORY_SEPARATOR . 'mygitignore']);

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    $this->assertFilesNotExist($this->dst, 'uic');
    $this->assertFilesExist($this->dst, 'uc');

    // Now, remove the .gitignore and push again.
    // We have to create 'uic' file since it was rightfully
    // removed during previous build run and the source repo branch was not
    // reset (uncommitted files would be removed, unless they are excluded
    // in .gitignore).
    $this->fixtureCreateFile($this->src, 'uic');
    $this->fixtureRemoveFile($this->src, 'mygitignore');
    $this->gitCommitAll($this->src, 'Commit number 3');
    $this->assertArtifactCommandSuccess();

    $this->gitAssertFixtureCommits(3, $this->dst, 'testbranch', ['Deployment commit'], FALSE);
    $this->gitAssertFilesCommitted($this->dst, ['f1', 'f2', 'uic'], 'testbranch');
    $this->assertFilesExist($this->dst, ['f1', 'f2', 'uic']);
    $this->gitAssertFilesNotCommitted($this->dst, ['uc'], 'testbranch');
  }

  public function testGitignoreCustomRemoveCommittedFiles(): void {
    $this->fixtureCreateFile($this->src, '.gitignore', ['ii', 'ic']);

    $this->fixtureCreateFile($this->src, 'ii');
    $this->fixtureCreateFile($this->src, 'ic');
    $this->fixtureCreateFile($this->src, 'd/cc');
    $this->fixtureCreateFile($this->src, 'd/ci');
    $this->gitCreateFixtureCommits(2);
    $this->gitCommitAll($this->src, 'Custom third commit');
    $this->fixtureCreateFile($this->src, 'ui');
    $this->fixtureCreateFile($this->src, 'uc');
    $this->gitAssertFilesCommitted($this->src, ['.gitignore', 'f1', 'f2', 'd/cc', 'd/ci']);
    $this->gitAssertFilesNotCommitted($this->src, ['ii', 'ic', 'ui', 'uc']);

    $this->fixtureCreateFile($this->src, 'mygitignore', ['f1', 'ii', 'ci', 'ui']);

    $this->assertArtifactCommandSuccess(['--gitignore' => $this->src . DIRECTORY_SEPARATOR . 'mygitignore']);

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Custom third commit', 'Deployment commit'], FALSE);
    $this->gitAssertFilesCommitted($this->dst, ['.gitignore', 'f2', 'ic', 'd/cc', 'uc'], 'testbranch');
    $this->gitAssertFilesNotCommitted($this->dst, ['f1', 'ii', 'd/ci', 'ui'], 'testbranch');
    $this->assertFilesExist($this->dst, ['f2', 'ic', 'd/cc', 'uc']);
    $this->assertFilesNotExist($this->dst, ['f1', 'ii', 'd/ci', 'ui']);
  }

  public function testGitignoreCustomAllowlisting(): void {
    $this->fixtureCreateFile($this->src, '.gitignore', ['ii', 'ic', 'd_ic', 'd_ii', '/vendor']);

    $this->fixtureCreateFile($this->src, 'ii');
    $this->fixtureCreateFile($this->src, 'ic');
    $this->fixtureCreateFile($this->src, 'cc');
    $this->fixtureCreateFile($this->src, 'ci');

    $this->fixtureCreateFile($this->src, 'd_cc/sub_cc');
    $this->fixtureCreateFile($this->src, 'd_ci/sub_ci');
    $this->fixtureCreateFile($this->src, 'd_ic/sub_ic');
    $this->fixtureCreateFile($this->src, 'd_ii/sub_ii');

    $this->fixtureCreateFile($this->src, 'vendor/ve_ii');
    $this->fixtureCreateFile($this->src, 'vendor_cc');
    $this->fixtureCreateFile($this->src, 'vendor_com with space com.txt');
    $this->fixtureCreateFile($this->src, 'dir_other/vendor/ve_cc');

    $this->gitCreateFixtureCommits(2);

    $this->gitCommitAll($this->src, 'Custom third commit');

    $this->gitAssertFilesCommitted($this->src, [
      '.gitignore', 'f1', 'f2',
      'cc', 'ci',
      'd_cc/sub_cc', 'd_ci/sub_ci',
      'vendor_cc', 'dir_other/vendor/ve_cc', 'vendor_com with space com.txt',
    ]);

    $this->gitAssertFilesNotCommitted($this->src, [
      'ii', 'ic', 'ui', 'uc', 'ud',
      'd_ic/sub_ic', 'd_ii/sub_ii',
      'vendor/ve_ii',
    ]);

    $this->fixtureCreateFile($this->src, 'ui');
    $this->fixtureCreateFile($this->src, 'uc');
    $this->fixtureCreateFile($this->src, 'ud');
    $this->fixtureCreateFile($this->src, 'd_ui/sub_ui');
    $this->fixtureCreateFile($this->src, 'd_uc/sub_uc');
    $this->fixtureCreateFile($this->src, 'd_ud/sub_ud');

    // Now, create a custom .gitignore and add non-ignored files
    // (allowlisting).
    $this->fixtureCreateFile($this->src, 'mygitignore', [
      '/*',
      '!f2', '!ic', '!cc', '!uc',
      '!d_cc', '!d_ic', '!d_uc',
      '!vendor',
    ]);

    // Run the build.
    $this->assertArtifactCommandSuccess([
      '-vvv' => TRUE,
      '--gitignore' => $this->src . DIRECTORY_SEPARATOR . 'mygitignore',
    ]);

    $this->gitAssertFixtureCommits(2, $this->dst, 'testbranch', ['Custom third commit', 'Deployment commit'], FALSE);

    $this->gitAssertFilesCommitted($this->dst, [
      'f2', 'ic', 'cc', 'uc',
      'd_cc/sub_cc', 'd_ic/sub_ic', 'd_uc/sub_uc',
      'vendor/ve_ii',
    ], 'testbranch');

    $this->gitAssertFilesNotCommitted($this->dst, [
      'f1', 'ii', 'ci', 'ui', 'ud',
      'd_ci/sub_ci', 'd_ii/sub_ii', 'd_ui/sub_ui', 'd_ud/sub_ud',
      'vendor_cc', 'dir_other/vendor/ve_cc', 'vendor_com with space com.txt',
    ], 'testbranch');

    $this->assertFilesExist($this->dst, [
      'f2', 'ic', 'cc', 'uc',
      'd_cc/sub_cc', 'd_ic/sub_ic', 'd_uc/sub_uc',
      'vendor/ve_ii',
    ]);
    $this->assertFilesNotExist($this->dst, [
      'f1', 'ii', 'ci', 'ui', 'ud',
      'd_ci/sub_ci',
      'd_ii/sub_ii', 'd_ui/sub_ui', 'd_ud/sub_ud',
      'vendor_cc', 'dir_other/vendor/ve_cc', 'vendor_com with space com.txt',
    ]);
  }

  public function testBuildTag(): void {
    $this->gitCreateFixtureCommits(2);
    $this->gitAddTag($this->src, 'tag1');

    $this->assertArtifactCommandSuccess(['--branch' => '[tags]'], 'tag1');

    $this->gitAssertFixtureCommits(2, $this->dst, 'tag1', ['Deployment commit']);
  }

  public function testBuildMultipleTags(): void {
    $this->gitCreateFixtureCommits(2);
    $this->gitAddTag($this->src, 'tag1');
    $this->gitAddTag($this->src, 'tag2');

    $this->assertArtifactCommandSuccess(['--branch' => '[tags]'], 'tag1-tag2');
    $this->gitAssertFixtureCommits(2, $this->dst, 'tag1-tag2', ['Deployment commit']);

    $this->gitCreateFixtureCommit(3);
    $this->gitAddTag($this->src, 'tag3');
    $this->assertArtifactCommandSuccess(['--branch' => '[tags]'], 'tag3');
    $this->gitAssertFixtureCommits(3, $this->dst, 'tag3', ['Deployment commit']);
  }

  public function testBuildMultipleTagsMissingTags(): void {
    $this->gitCreateFixtureCommits(2);
    $this->gitAddTag($this->src, 'tag1');
    $this->gitCreateFixtureCommit(3);

    $this->assertArtifactCommandFailure(['--branch' => '[tags]']);
  }

  public function testBuildMultipleTagsDelimiter(): void {
    $this->gitCreateFixtureCommits(2);
    $this->gitAddTag($this->src, 'tag1');
    $this->gitAddTag($this->src, 'tag2');

    $this->assertArtifactCommandSuccess(['--branch' => '[tags:__]'], 'tag1__tag2');

    $this->gitAssertFixtureCommits(2, $this->dst, 'tag1__tag2', ['Deployment commit']);
  }

}
