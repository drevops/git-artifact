<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use CzProject\GitPhp\GitException;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test ArtifactGitRepository class.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
#[CoversClass(ArtifactGitRepository::class)]
class ArtifactGitRepositoryTest extends AbstractUnitTestCase {

  /**
   * Test push force.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testPushForce(): void {
    $sourceRepo = $this->git->open($this->src);
    $sourceRepo->commit('Source commit 1', ['--allow-empty']);

    $destinationRepo = $this->git->open($this->dst);
    $destinationRepo->commit('Destination commit 1', ['--allow-empty']);
    $lastCommit = $destinationRepo->getLastCommit();
    $this->assertEquals('Destination commit 1', $lastCommit->getSubject());

    $sourceRepo->addRemote('dst', $this->dst);
    $sourceRepo->pushForce('dst', 'refs/heads/master:refs/heads/master');
    $lastCommit = $destinationRepo->getLastCommit();
    $this->assertEquals('Source commit 1', $lastCommit->getSubject());
  }

  /**
   * Test list files.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testListFiles(): void {
    $sourceRepo = $this->git->open($this->src);
    // Test list ignored files.
    $gitIgnoreFile = $this->src . DIRECTORY_SEPARATOR . '.gitignore';
    file_put_contents($gitIgnoreFile, '');
    $this->assertFileExists($gitIgnoreFile);
    $files = $sourceRepo->listIgnoredFilesFromGitIgnoreFile($gitIgnoreFile);
    $this->assertEquals([], $files);

    $this->gitCreateFixtureFile($this->src, 'test-ignore-1');
    $this->gitCreateFixtureFile($this->src, 'test-ignore-2');
    $sourceRepo->commitAllChanges('Test list ignored files.');

    file_put_contents($gitIgnoreFile, "test-ignore-1\ntest-ignore-2");
    $files = $sourceRepo->listIgnoredFilesFromGitIgnoreFile($gitIgnoreFile);
    $this->assertEquals(['test-ignore-1', 'test-ignore-2'], $files);

    // Test list other files.
    $otherFiles = $sourceRepo->listOtherFiles();
    $this->assertEquals([], $otherFiles);
    $this->gitCreateFixtureFile($this->src, 'other-file-1');
    $this->gitCreateFixtureFile($this->src, 'other-file-2');
    $otherFiles = $sourceRepo->listOtherFiles();
    $this->assertEquals(['other-file-1', 'other-file-2'], $otherFiles);
  }

  /**
   * Test get commits.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testGetCommits(): void {
    $sourceRepo = $this->git->open($this->src);

    $this->gitCreateFixtureFile($this->src, 'test-commit-file1');
    $sourceRepo->commitAllChanges('Add file 1');
    $commits = $sourceRepo->getCommits();

    $this->assertEquals(['Add file 1'], $commits);

    $this->gitCreateFixtureFile($this->src, 'test-commit-file2');
    $sourceRepo->commitAllChanges('Add file 2');
    $commits = $sourceRepo->getCommits();

    $this->assertEquals(['Add file 2', 'Add file 1'], $commits);
  }

  /**
   * Test reset hard command.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testResetHard(): void {
    $sourceRepo = $this->git->open($this->src);
    $file = $this->gitCreateFixtureFile($this->src, 'test-file1');
    file_put_contents($file, 'Content example');
    $sourceRepo->commitAllChanges('Add file 1');
    $this->assertEquals('Content example', file_get_contents($file));

    file_put_contents($file, 'New content');
    $this->assertEquals('New content', file_get_contents($file));

    $sourceRepo->resetHard();
    $this->assertEquals('Content example', file_get_contents($file));
  }

  /**
   * Test clean force command.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testCleanForce(): void {
    $sourceRepo = $this->git->open($this->src);
    $this->gitCreateFixtureFile($this->src, 'test-file1');
    $sourceRepo->commitAllChanges('Add file 1');
    $file = $this->gitCreateFixtureFile($this->src, 'test-file2');
    $this->assertFileExists($file);

    $sourceRepo->cleanForce();
    $this->assertFileDoesNotExist($file);
  }

  /**
   * Test branch command.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testBranch(): void {
    $sourceRepo = $this->git->open($this->src);
    $this->gitCreateFixtureFile($this->src, 'test-file1');
    $sourceRepo->commitAllChanges('Add file 1');
    // Test switch.
    $sourceRepo->switchToBranch('branch1', TRUE);
    $this->assertEquals('branch1', $sourceRepo->getCurrentBranchName());
    $sourceRepo->switchToBranch('branch2', TRUE);
    $this->assertEquals('branch2', $sourceRepo->getCurrentBranchName());
    $sourceRepo->switchToBranch('branch1');
    $this->assertEquals('branch1', $sourceRepo->getCurrentBranchName());
    // Test remove branch.
    $this->assertEquals(['branch1', 'branch2', 'master'], $sourceRepo->getBranches());
    $sourceRepo->removeBranch('master');
    $this->assertEquals(['branch1', 'branch2'], $sourceRepo->getBranches());
    $sourceRepo->removeBranch('branch2', TRUE);
    $this->assertEquals(['branch1'], $sourceRepo->getBranches());

    $sourceRepo->removeBranch('', TRUE);
    $this->assertEquals(['branch1'], $sourceRepo->getBranches());
  }

  /**
   * Test commit all changes.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testCommitAllChanges(): void {
    $sourceRepo = $this->git->open($this->src);
    $file = $this->gitCreateFixtureFile($this->src, 'test-file1');
    $sourceRepo->addFile($file);
    $sourceRepo->commit('Add file 1');
    $this->assertEquals(['Add file 1'], $sourceRepo->getCommits());

    $this->gitCreateFixtureFile($this->src, 'test-file2');
    $sourceRepo->commitAllChanges('Commit all changes.');
    $this->assertEquals(['Commit all changes.', 'Add file 1'], $sourceRepo->getCommits());
  }

  /**
   * Test list commited files.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testListCommittedFiles(): void {
    $sourceRepo = $this->git->open($this->src);
    $sourceRepo->commit('Commit 1', ['--allow-empty']);
    $this->assertEquals([], $sourceRepo->listCommittedFiles());

    $file = $this->gitCreateFixtureFile($this->src, 'file-1');
    $this->assertEquals([], $sourceRepo->listCommittedFiles());

    $sourceRepo->addFile($file);
    $sourceRepo->commit('Add file 1');
    $this->assertEquals(['file-1'], $sourceRepo->listCommittedFiles());
  }

  /**
   * Test set config.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testSetConfigReceiveDenyCurrentBranchIgnore(): void {
    $sourceRepo = $this->git->open($this->src);
    try {
      $receiveDenyCurrentBranch = $sourceRepo->execute('config', 'receive.denyCurrentBranch');
    }
    catch (GitException) {
      $receiveDenyCurrentBranch = '';
    }
    $this->assertEquals('', $receiveDenyCurrentBranch);
    $sourceRepo->setConfigReceiveDenyCurrentBranchIgnore();
    $receiveDenyCurrentBranch = $sourceRepo->execute('config', 'receive.denyCurrentBranch');
    $this->assertEquals(['ignore'], $receiveDenyCurrentBranch);
  }

  /**
   * Test create tag commands.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testCreateTag(): void {
    $sourceRepo = $this->git->open($this->src);
    $sourceRepo->commit('Commit 1', ['--allow-empty']);
    $this->assertEquals(NULL, $sourceRepo->getTags());

    $sourceRepo->createAnnotatedTag('tag1', 'Hello tag 1');
    $this->assertEquals(['tag1'], $sourceRepo->getTags());

    $sourceRepo->createLightweightTag('tag2');
    $this->assertEquals(['tag1', 'tag2'], $sourceRepo->getTags());
  }

  /**
   * Test remote commands.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function testRemote(): void {
    $sourceRepo = $this->git->open($this->src);
    $this->assertEquals([], $sourceRepo->getRemotes());

    $sourceRepo->addRemote('dst', $this->dst);
    $this->assertEquals(['dst'], $sourceRepo->getRemotes());
    $this->assertTrue($sourceRepo->isRemoteExists('dst'));
    $sourceRepo->removeRemote('dst');
    $this->assertEquals([], $sourceRepo->getRemotes());
    $this->assertFalse($sourceRepo->isRemoteExists('dst'));

    $sourceRepo->removeRemote('dummy');
    $this->assertEquals([], $sourceRepo->getRemotes());
  }

  /**
   * Test is valid remote url.
   *
   * @throws \Exception
   */
  #[DataProvider('dataProviderIsValidRemoteUrl')]
  public function testIsValidRemoteUrl(?bool $expected, string $pathOrUri, string $type, bool $pass): void {
    if (!$pass) {
      $this->expectException(\InvalidArgumentException::class);
      ArtifactGitRepository::isValidRemoteUrl($pathOrUri, $type);
    }
    else {
      $this->assertEquals($expected, ArtifactGitRepository::isValidRemoteUrl($pathOrUri, $type));
    }
  }

  /**
   * Data provider.
   *
   * @return array<mixed>
   *   Data provider.
   */
  public static function dataProviderIsValidRemoteUrl(): array {
    return [
      [TRUE, 'git@github.com:foo/git-foo.git', 'uri', TRUE],
      [FALSE, 'git@github.com:foo/git-foo.git', 'local', TRUE],
      [TRUE, 'git@github.com:foo/git-foo.git', 'any', TRUE],
      [FALSE, '/no-existing/path', 'any', TRUE],
      [FALSE, '/no-existing/path', 'local', TRUE],
      [NULL, '/no-existing/path', 'custom', FALSE],
    ];
  }

  /**
   * Test is valid remote url.
   *
   * @throws \Exception
   */
  #[DataProvider('dataProviderIsValidBranchName')]
  public function testIsValidBranchName(bool $expected, string $branchName): void {
    $this->assertEquals($expected, ArtifactGitRepository::isValidBranchName($branchName));
  }

  /**
   * Data provider.
   *
   * @return array<mixed>
   *   Data provider.
   */
  public static function dataProviderIsValidBranchName(): array {
    return [
      [TRUE, 'branch'],
      [FALSE, '*/branch'],
      [FALSE, '*.branch'],
    ];
  }

}
