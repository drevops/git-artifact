<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Traits;

use DrevOps\GitArtifact\Traits\FilesystemTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Concrete class for testing FilesystemTrait.
 */
class FilesystemTraitTestClass {

  use FilesystemTrait;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->fs = new Filesystem();
  }

  /**
   * Public wrapper for fsGetRootDir().
   */
  public function callFsGetRootDir(): string {
    return $this->fsGetRootDir();
  }

  /**
   * Public wrapper for fsAssertPathsExist().
   *
   * @param string|array<string> $paths
   *   Paths to check.
   * @param bool $strict
   *   Strict mode.
   *
   * @return bool
   *   TRUE if paths exist.
   */
  public function callFsAssertPathsExist($paths, bool $strict = TRUE): bool {
    return $this->fsAssertPathsExist($paths, $strict);
  }

  /**
   * Public wrapper for fsGetAbsolutePath().
   */
  public function callFsGetAbsolutePath(string $file, ?string $root = NULL): string {
    return $this->fsGetAbsolutePath($file, $root);
  }

  /**
   * Set fsRootDir for testing.
   */
  public function setFsRootDir(string $dir): void {
    $this->fsRootDir = $dir;
  }

  /**
   * Reset fsRootDir for testing.
   */
  public function resetFsRootDir(): void {
    unset($this->fsRootDir);
  }

}
