<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Trait FilesystemTrait.
 */
trait FilesystemTrait
{

    /**
     * Current directory where call originated.
     *
     * @var string
     */
    protected $fsRootDir;

    /**
     * File system for custom commands.
     */
    protected Filesystem $fsFileSystem;

    /**
     * Stack of original current working directories.
     *
     * This is used throughout commands to track working directories.
     * Usually, each command would call setCwd() in the beginning and
     * restoreCwd() at the end of the run.
     *
     * @var array<string>
     */
    protected $fsOriginalCwdStack = [];

    /**
     * Set root directory path.
     *
     * @param string|null $path
     *   The path of the root directory.
     *
     * @throws \Exception
     */
    protected function fsSetRootDir(string $path = null): void
    {
        $path = empty($path) ? $this->fsGetRootDir() : $this->fsGetAbsolutePath($path);
        $this->fsPathsExist($path);
        $this->fsRootDir = $path;
    }

    /**
     * Get root directory.
     *
     * @return string
     *   Get value of the root directory, the directory where the
     *   script was started from or current working directory.
     */
    protected function fsGetRootDir(): string
    {
        if ($this->fsRootDir) {
            return $this->fsRootDir;
        }

        if (isset($_SERVER['PWD'])) {
            return $_SERVER['PWD'];
        }

        return (string) getcwd();
    }

    /**
     * Set current working directory.
     *
     * It is important to note that this should be called in pair with
     * cwdRestore().
     *
     * @param string $dir
     *   Path to the current directory.
     */
    protected function fsCwdSet(string $dir): void
    {
        chdir($dir);
        $this->fsOriginalCwdStack[] = $dir;
    }

    /**
     * Set current working directory to a previously saved path.
     *
     * It is important to note that this should be called in pair with cwdSet().
     */
    protected function fsCwdRestore(): void
    {
        $dir = array_shift($this->fsOriginalCwdStack);
        if ($dir) {
            chdir($dir);
        }
    }

    /**
     * Get current working directory.
     *
     * @return string
     *   Full path of current working directory.
     */
    protected function fsCwdGet(): string
    {
        return (string) getcwd();
    }

    /**
     * Check that a command is available in current session.
     *
     * @param string $command
     *   Command to check.
     *
     * @return bool
     *   TRUE if command is available, FALSE otherwise.
     *
     */
    protected function fsIsCommandAvailable(string $command): bool
    {
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
    protected function fsGetAbsolutePath(string $file, string $root = null): string
    {
        if ($this->fsFileSystem->isAbsolutePath($file)) {
            return $this->realpath($file);
        }
        $root = $root ? $root : $this->fsGetRootDir();
        $root = $this->realpath($root);
        $file = $root.DIRECTORY_SEPARATOR.$file;

        return $this->realpath($file);
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
    protected function fsPathsExist($paths, bool $strict = true): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        if (!$this->fsFileSystem->exists($paths)) {
            if ($strict) {
                throw new \Exception(sprintf('One of the files or directories does not exist: %s', implode(', ', $paths)));
            }

            return false;
        }

        return true;
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
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function realpath(string $path): string
    {
        // Whether $path is unix or not.
        $unipath = $path === '' || $path[0] !== '/';
        $unc = str_starts_with($path, '\\\\');
        // Attempt to detect if path is relative in which case, add cwd.
        if (!str_contains($path, ':') && $unipath && !$unc) {
            $path = getcwd().DIRECTORY_SEPARATOR.$path;
            if ($path[0] === '/') {
                $unipath = false;
            }
        }

        // Resolve path parts (single dot, double dot and double delimiters).
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), static function ($part) : bool {
            return strlen($part) > 0;
        });

        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' === $part) {
                continue;
            }
            if ('..' === $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        $path = implode(DIRECTORY_SEPARATOR, $absolutes);
        // Resolve any symlinks.
        if (function_exists('readlink') && file_exists($path) && linkinfo($path) > 0) {
            $path = readlink($path);
        }
        // Put initial separator that could have been lost.
        $path = $unipath ? $path : '/'.$path;

        /* @phpstan-ignore-next-line */
        return $unc ? '\\\\'.$path : $path;
    }
}
