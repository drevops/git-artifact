<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Traits;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Trait FilesystemTrait.
 */
trait FilesystemTrait {

  /**
   * Current directory where call originated.
   */
  protected string $fsRootDir;

  /**
   * File system for custom commands.
   */
  protected Filesystem $fs;

  /**
   * Stack of original current working directories.
   *
   * This is used throughout commands to track working directories.
   * Usually, each command would call setCwd() in the beginning and
   * restoreCwd() at the end of the run.
   *
   * @var array<string>
   */
  protected array $fsOriginalCwdStack = [];

  /**
   * Set root directory path.
   *
   * @param string|null $path
   *   The path of the root directory.
   *
   * @return static
   *   The called object.
   */
  protected function fsSetRootDir(?string $path = NULL): static {
    $path = empty($path) ? $this->fsGetRootDir() : $this->fsGetAbsolutePath($path);
    $this->fsAssertPathsExist($path);
    $this->fsRootDir = $path;

    return $this;
  }

  /**
   * Get root directory.
   *
   * @return string
   *   Get value of the root directory, the directory where the
   *   script was started from or current working directory.
   */
  protected function fsGetRootDir(): string {
    if (!isset($this->fsRootDir)) {
      if (isset($_SERVER['PWD']) && is_string($_SERVER['PWD']) && !empty($_SERVER['PWD'])) {
        $this->fsRootDir = $_SERVER['PWD'];
      }
      else {
        $this->fsRootDir = (string) getcwd();
      }
    }

    return $this->fsRootDir;
  }

  /**
   * Check that a command is available in current session.
   *
   * @param string $command
   *   Command to check.
   *
   * @return bool
   *   TRUE if command is available, FALSE otherwise.
   */
  protected function fsIsCommandAvailable(string $command): bool {
    $process = new Process(['which', $command]);
    $process->run();

    return $process->isSuccessful();
  }

  /**
   * Get absolute path for provided file.
   *
   * @param string $file
   *   File to resolve. If absolute, no resolution will be performed.
   * @param string|null $root
   *   Optional path to root dir. If not provided, internal root path is used.
   *
   * @return string
   *   Absolute path for provided file.
   */
  protected function fsGetAbsolutePath(string $file, ?string $root = NULL): string {
    if ($this->fs->isAbsolutePath($file)) {
      return static::fsRealpath($file);
    }

    $root = $root ? $root : $this->fsGetRootDir();
    $root = static::fsRealpath($root);
    $file = $root . DIRECTORY_SEPARATOR . $file;

    return static::fsRealpath($file);
  }

  /**
   * Check that path exists.
   *
   * @param string|array<string> $paths
   *   File name or array of file names to check.
   * @param bool $strict
   *   If TRUE and the file does not exist, an exception will be thrown.
   *   Defaults to TRUE.
   *
   * @return bool
   *   TRUE if file exists and FALSE if not, but only if $strict is FALSE.
   *
   * @throws \Exception
   *   If at least one file does not exist.
   */
  protected function fsAssertPathsExist($paths, bool $strict = TRUE): bool {
    $paths = is_array($paths) ? $paths : [$paths];

    if (!$this->fs->exists($paths)) {
      if ($strict) {
        throw new \Exception(sprintf('One of the files or directories does not exist: %s', implode(', ', $paths)));
      }

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Replacement for PHP's `realpath` resolves non-existing paths.
   *
   * The main deference is that it does not return FALSE on non-existing
   * paths.
   *
   * @param string $path
   *   Path that needs to be resolved.
   *
   * @return string
   *   Resolved path.
   *
   * @see https://stackoverflow.com/a/29372360/712666
   */
  protected static function fsRealpath(string $path): string {
    // Whether $path is unix or not.
    $is_unix_path = $path === '' || $path[0] !== '/';
    $unc = str_starts_with($path, '\\\\');

    // Attempt to detect if path is relative in which case, add cwd.
    if (!str_contains($path, ':') && $is_unix_path && !$unc) {
      $path = getcwd() . DIRECTORY_SEPARATOR . $path;
      if ($path[0] === '/') {
        $is_unix_path = FALSE;
      }
    }

    // Resolve path parts (single dot, double dot and double delimiters).
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), static function ($part): bool {
      return strlen($part) > 0;
    });

    $absolutes = [];
    foreach ($parts as $part) {
      if ('.' === $part) {
        continue;
      }
      if ('..' === $part) {
        array_pop($absolutes);
      }
      else {
        $absolutes[] = $part;
      }
    }

    $path = implode(DIRECTORY_SEPARATOR, $absolutes);
    // Put initial separator that could have been lost.
    $path = $is_unix_path ? $path : '/' . $path;
    $path = $unc ? '\\\\' . $path : $path;

    // Resolve any symlinks.
    if (function_exists('readlink') && file_exists($path) && is_link($path) > 0) {
      $path = readlink($path);

      if (!$path) {
        // @codeCoverageIgnoreStart
        throw new \Exception(sprintf('Could not resolve symlink for path: %s', $path));
        // @codeCoverageIgnoreEnd
      }
    }

    if (str_starts_with($path, sys_get_temp_dir())) {
      $tmp_realpath = realpath(sys_get_temp_dir());
      if ($tmp_realpath) {
        $path = str_replace(sys_get_temp_dir(), $tmp_realpath, $path);
      }
    }

    return $path;
  }

}
