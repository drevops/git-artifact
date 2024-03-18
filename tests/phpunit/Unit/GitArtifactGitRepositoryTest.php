<?php

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\GitArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test GitArtifactGitRepository class.
 */
#[CoversClass(GitArtifactGitRepository::class)]
class GitArtifactGitRepositoryTest extends AbstractUnitTestCase {

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

    $otherFiles = $sourceRepo->listOtherFiles();
    $this->assertEquals([], $otherFiles);
    $this->gitCreateFixtureFile($this->src, 'other-file-1');
    $this->gitCreateFixtureFile($this->src, 'other-file-2');
    $otherFiles = $sourceRepo->listOtherFiles();
    $this->assertEquals(['other-file-1', 'other-file-2'], $otherFiles);
  }

}
