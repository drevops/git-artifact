<?php

namespace IntegratedExperts\Robo;

use Robo\Contract\VerbosityThresholdInterface;
use Robo\LoadAllTasks;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Trait FilesystemTrait.
 *
 * @package IntegratedExperts\Robo
 */
trait FilesystemTrait
{

    use LoadAllTasks;

    /**
     * Current directory where call originated.
     *
     * @var string
     */
    protected $fsRootDir;

    /**
     * File system for custom commands.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fsFileSystem;

    /**
     * Finder to perform command lookup.
     *
     * @var \Symfony\Component\Finder\Finder
     */
    protected $fsFinder;

    /**
     * Stack of original current working directories.
     *
     * This is used throughout commands to track working directories.
     * Usually, each command would call setCwd() in the beginning and
     * restoreCwd() at the end of the run.
     *
     * @var array
     */
    protected $fsOriginalCwdStack = [];

    /**
     * FilesystemTrait constructor.
     */
    public function __construct()
    {
        $this->fsFileSystem = new Filesystem();
        $this->fsFinder = new Finder();
    }

    /**
     * Set root directory path.
     */
    protected function fsSetRootDir($path)
    {
        $path = !empty($path) ? $this->fsGetAbsolutePath($path) : $this->fsGetRootDir();
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
    protected function fsGetRootDir()
    {
        if ($this->fsRootDir) {
            return $this->fsRootDir;
        } elseif (isset($_SERVER['PWD'])) {
            return $_SERVER['PWD'];
        }

        return getcwd();
    }

    /**
     * Set current working directory.
     *
     * It is important to note that this should be called in pair with
     * cwdRestore().
     */
    protected function fsCwdSet($dir)
    {
        chdir($dir);
        $this->fsOriginalCwdStack[] = $dir;
    }

    /**
     * Set current working directory to a previously saved path.
     *
     * It is important to note that this should be called in pair with cwdSet().
     */
    protected function fsCwdRestore()
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
    protected function fsCwdGet()
    {
        return getcwd();
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
    protected function fsIsCommandAvailable($command)
    {
        $result = $this->taskExecStack()
            ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG)
            ->printOutput(false)
            ->exec('which '.$command)
            ->run();

        return $result->wasSuccessful();
    }

    /**
     * Get absolute path for provided file.
     *
     * @param string $file
     *   File to resolve. If absolute, no resolution will be performed.
     * @param string $root
     *   Optional path to root dir. If not provided, internal root path is used.
     *
     * @return string
     *   Absolute path for provided file.
     */
    protected function fsGetAbsolutePath($file, $root = null)
    {
        if ($this->fsFileSystem->isAbsolutePath($file)) {
            return $this->realpath($file);
        }
        $root = $root ? $root : $this->fsGetRootDir();
        $root = $this->realpath($root);
        $file = $root.DIRECTORY_SEPARATOR.$file;
        $file = $this->realpath($file);

        return $file;
    }

    /**
     * Check that path exists.
     *
     * @param string|array $paths
     *   File name or array of file names to check.
     * @param bool         $strict
     *   If TRUE and the file does not exist, an exception will be thrown.
     *   Defaults to TRUE.
     *
     * @return bool
     *   TRUE if file exists and FALSE if not, but only if $strict is FALSE.
     *
     * @throws \Exception
     *   If at least one file does not exist.
     */
    protected function fsPathsExist($paths, $strict = true)
    {
        $paths = is_array($paths) ? $paths : [$paths];
        if (!$this->fsFileSystem->exists($paths)) {
            if ($strict) {
                throw new \Exception(sprintf('One of the files or directories does not exist: %s', implode(', ', $paths)));
            } else {
                return false;
            }
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
     */
    protected function realpath($path)
    {
        // Whether $path is unix or not.
        $unipath = strlen($path) == 0 || $path{0} != '/';
        $unc = substr($path, 0, 2) == '\\\\' ? true : false;
        // Attempt to detect if path is relative in which case, add cwd.
        if (strpos($path, ':') === false && $unipath && !$unc) {
            $path = getcwd().DIRECTORY_SEPARATOR.$path;
            if ($path{0} == '/') {
                $unipath = false;
            }
        }

        // Resolve path parts (single dot, double dot and double delimiters).
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
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
        $path = !$unipath ? '/'.$path : $path;
        $path = $unc ? '\\\\'.$path : $path;

        return $path;
    }
}
