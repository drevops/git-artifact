<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for file permissions preservation in artifacts.
 */
#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class FilePermissionsTest extends FunctionalTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->mode = ArtifactCommand::MODE_FORCE_PUSH;
  }

  public function testFilePermissions(): void {
    $permissions = [0644, 0755];
    $files = [];

    foreach ($permissions as $perm) {
      $filename = sprintf('file_%o.txt', $perm);
      $file_path = $this->fixtureCreateFile($this->src, $filename, sprintf("Content with %o permissions\n", $perm));
      chmod($file_path, $perm);
      $files[$filename] = $perm;

      $this->assertSame($perm, fileperms($file_path) & 0777);
    }

    $this->gitCommitAll($this->src, 'Added files with various permissions');

    $this->assertArtifactCommandSuccess(['--branch' => 'testbranch']);

    $this->gitCheckout($this->dst, 'testbranch');

    foreach ($files as $filename => $expected_perm) {
      $dst_file = $this->dst . DIRECTORY_SEPARATOR . $filename;
      $this->assertFileExists($dst_file);
      $actual_perm = fileperms($dst_file) & 0777;
      $this->assertSame($expected_perm, $actual_perm,
        sprintf('File %s should preserve %o permissions, got %o', $filename, $expected_perm, $actual_perm));
    }
  }

}
