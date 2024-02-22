<?php

declare(strict_types=1);

namespace DrevOps\Robo;

use Robo\Exception\AbortTasksException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class Artefact.
 *
 * Robo task to package current repository with all dependencies and send it
 * to the remote repository.
 */
trait ArtefactTrait
{

    use FilesystemTrait {
        FilesystemTrait::__construct as private __artifactFsConstruct;
    }
    use GitTrait;
    use TokenTrait;

    /**
     * Mode in which current build is going to run.
     *
     * Available modes: branch, force-push, diff.
     *
     * @var string
     */
    protected $mode;

    /**
     * Original branch in current repository.
     *
     * @var string|null
     */
    protected $originalBranch;

    /**
     * Destination branch with optional tokens.
     *
     * @var string
     */
    protected $dstBranch;

    /**
     * Local branch where artifact will be built.
     *
     * @var string
     */
    protected $artifactBranch;

    /**
     * Remote name.
     *
     * @var string
     */
    protected $remoteName;

    /**
     * Gitignore file to be used during artifact creation.
     *
     * If not set, the current `.gitignore` will be used, if any.
     *
     * @var string
     */
    protected $gitignoreFile;

    /**
     * Commit message with optional tokens.
     *
     * @var string
     */
    protected $message;

    /**
     * Flag to specify if push is required or should be using dry run.
     *
     * @var bool
     */
    protected $needsPush;

    /**
     * Flag to specify if cleanup is required to run after the build.
     *
     * @var bool
     */
    protected $needCleanup;

    /**
     * Path to report file.
     *
     * @var string
     */
    protected $report;

    /**
     * Flag to show changes made to the repo by the build in the output.
     *
     * @var bool
     */
    protected $showChanges;

    /**
     * Artefact build result.
     *
     * @var bool
     */
    protected $result = false;

    /**
     * Flag to print debug information.
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Internal option to set current timestamp.
     *
     * @var int
     */
    protected $now;

    /**
     * Artefact constructor.
     */
    public function __construct()
    {
        $this->__artifactFsConstruct();
    }

    /**
     * Push artifact of current repository to remote git repository.
     *
     * @param string $remote
     *   Path to the remote git repository.
     * @param array $opts
     *   Options.
     *
     * @option $branch Destination branch with optional tokens.
     * @option $debug Print debug information.
     * @option $gitignore Path to gitignore file to replace current .gitignore.
     * @option $message Commit message with optional tokens.
     * @option $mode Mode of artifact build: branch, force-push or diff.
     *   Defaults to force-push.
     * @option $now Internal value used to set internal time.
     * @option $no-cleanup Do not cleanup after run.
     * @option $push Push artifact to the remote repository. Defaults to FALSE.
     * @option $report Path to the report file.
     * @option $root Path to the root for file path resolution. If not
     *         specified, current directory is used.
     * @option $show-changes Show changes made to the repo by the build in the
     *         output.
     * @option $src Directory where source repository is located. If not
     *   specified, root directory is used.
     *
     * @throws AbortTasksException
     * @throws \Exception
     *
     * @phpstan-ignore-next-line
     */
    public function artifact(string $remote, array $opts = [
        'branch' => '[branch]',
        'debug' => false,
        'gitignore' => InputOption::VALUE_REQUIRED,
        'message' => 'Deployment commit',
        'mode' => 'force-push',
        'no-cleanup' => false,
        'now' => InputOption::VALUE_REQUIRED,
        'push' => false,
        'report' => InputOption::VALUE_REQUIRED,
        'root' => InputOption::VALUE_REQUIRED,
        'show-changes' => false,
        'src' => InputOption::VALUE_REQUIRED,
    ]): void
    {
        try {
            $error = null;

            $this->checkRequirements();
            $this->resolveOptions($opts);

            $this->printDebug('Debug messages enabled');

            $this->gitSetDst($remote);

            $this->showInfo();
            $this->prepareArtefact();

            if ($this->needsPush) {
                $this->doPush();
            } else {
                $this->yell('Cowardly refusing to push to remote. Use --push option to perform an actual push.');
            }
            $this->result = true;
        } catch (\Exception $exception) {
            // Capture message and allow to rollback.
            $error = $exception->getMessage();
        }

        if ($this->report) {
            $this->dumpReport();
        }

        if ($this->needCleanup) {
            $this->cleanup();
        }

        if ($this->result) {
            $this->say('Deployment finished successfully.');
        } else {
            $this->say('Deployment failed.');
            throw new AbortTasksException((string) $error);
        }
    }

    /**
     * Prepare artifact to be then deployed.
     *
     * @throws \Exception
     */
    protected function prepareArtefact(): void
    {
        $this->gitSwitchToBranch($this->src, $this->artifactBranch, true);

        $this->removeSubRepos($this->src);
        $this->disableLocalExclude($this->src);

        if (!empty($this->gitignoreFile)) {
            $this->replaceGitignore($this->gitignoreFile, $this->src);
            $this->gitAddAll($this->src);
            $this->removeIgnoredFiles($this->src);
        } else {
            $this->gitAddAll($this->src);
        }

        $this->removeOtherFiles($this->src);

        $result = $this->gitCommit($this->src, $this->message);

        if ($this->showChanges) {
            $this->say(sprintf('Added changes: %s', $result->getMessage()));
        }
    }

    /**
     * Cleanup after build.
     *
     * @throws \Exception
     */
    protected function cleanup(): void
    {
        $this->restoreLocalExclude($this->src);
        $this->gitSwitchToBranch($this->src, (string) $this->originalBranch);
        $this->gitRemoveBranch($this->src, $this->artifactBranch);
        $this->gitRemoveRemote($this->src, $this->remoteName);
    }

    /**
     * Perform actual push to remote.
     *
     * @throws \Exception
     */
    protected function doPush(): void
    {
        if (!$this->gitRemoteExists($this->src, $this->remoteName)) {
            $this->gitAddRemote($this->src, $this->remoteName, $this->dst);
        }

        try {
            $result = $this->gitPush($this->src, $this->artifactBranch, $this->remoteName, $this->dstBranch, $this->mode === self::modeForcePush());
            $this->result = $result->wasSuccessful();
        } catch (\Exception $exception) {
            // Re-throw the message with additional context.
            throw new \Exception(sprintf('Error occurred while pushing branch "%s": %s', $this->dstBranch, $exception->getMessage()), $exception->getCode(), $exception);
        }

        if ($this->result) {
            $this->sayOkay(sprintf('Pushed branch "%s" with commit message "%s"', $this->dstBranch, $this->message));
        } else {
            // We should never reach this - any problems with git push should
            // throw an exception, that we catching above.
            throw new \Exception(sprintf('Error occurred while pushing branch "%s" with commit message "%s"', $this->dstBranch, $this->message));
        }
    }

    /**
     * Resolve and validate CLI options values into internal values.
     *
     * @param array $options
     *   Array of CLI options.
     *
     * @throws \Exception
     *
     * @phpstan-ignore-next-line
     */
    protected function resolveOptions(array $options): void
    {
        $this->now = empty($options['now']) ? time() : (int) $options['now'];

        $this->debug = !empty($options['debug']);

        $this->remoteName = 'dst';

        $this->fsSetRootDir($options['root']);

        // Default source to the root directory.
        $srcPath = empty($options['src']) ? $this->fsGetRootDir() : $this->fsGetAbsolutePath($options['src']);
        $this->gitSetSrcRepo($srcPath);

        $this->originalBranch = $this->resolveOriginalBranch($this->src);
        $this->setDstBranch($options['branch']);
        $this->artifactBranch = $this->dstBranch.'-artifact';

        $this->setMessage($options['message']);

        if (!empty($options['gitignore'])) {
            $this->setGitignoreFile($options['gitignore']);
        }

        $this->showChanges = !empty($options['show-changes']);

        $this->needCleanup = empty($options['no-cleanup']);

        $this->needsPush = !empty($options['push']);

        $this->report = empty($options['report']) ? null : $options['report'];

        $this->setMode($options['mode'], $options);
    }

    /**
     * Show artifact build information.
     */
    protected function showInfo(): void
    {
        $this->writeln('----------------------------------------------------------------------');
        $this->writeln(' Artefact information');
        $this->writeln('----------------------------------------------------------------------');
        $this->writeln(' Build timestamp:       '.date('Y/m/d H:i:s', $this->now));
        $this->writeln(' Mode:                  '.$this->mode);
        $this->writeln(' Source repository:     '.$this->src);
        $this->writeln(' Remote repository:     '.$this->dst);
        $this->writeln(' Remote branch:         '.$this->dstBranch);
        $this->writeln(' Gitignore file:        '.($this->gitignoreFile ? $this->gitignoreFile : 'No'));
        $this->writeln(' Will push:             '.($this->needsPush ? 'Yes' : 'No'));
        $this->writeln('----------------------------------------------------------------------');
    }

    /**
     * Dump artifact report to a file.
     */
    protected function dumpReport(): void
    {
        $lines[] = '----------------------------------------------------------------------';
        $lines[] = ' Artefact report';
        $lines[] = '----------------------------------------------------------------------';
        $lines[] = ' Build timestamp:   '.date('Y/m/d H:i:s', $this->now);
        $lines[] = ' Mode:              '.$this->mode;
        $lines[] = ' Source repository: '.$this->src;
        $lines[] = ' Remote repository: '.$this->dst;
        $lines[] = ' Remote branch:     '.$this->dstBranch;
        $lines[] = ' Gitignore file:    '.($this->gitignoreFile ? $this->gitignoreFile : 'No');
        $lines[] = ' Commit message:    '.$this->message;
        $lines[] = ' Push result:       '.($this->result ? 'Success' : 'Failure');
        $lines[] = '----------------------------------------------------------------------';

        $this->fsFileSystem->dumpFile($this->report, implode(PHP_EOL, $lines));
    }

    /**
     * Set build mode.
     *
     * @param string $mode
     *   Mode to set.
     * @param array $options
     *   Array of CLI options.
     *
     * @phpstan-ignore-next-line
     */
    protected function setMode(string $mode, array $options): void
    {
        $this->say(sprintf('Running in "%s" mode', $mode));

        switch ($mode) {
            case self::modeForcePush():
                // Intentionally empty.
                break;

            case self::modeBranch():
                if (!$this->hasToken($options['branch'])) {
                    $this->say('WARNING! Provided branch name does not have a token. Pushing of the artifact into this branch will fail on second and follow up pushes to remote. Consider adding tokens with unique values to the branch name.');
                }
                break;

            case self::modeDiff():
                throw new \RuntimeException('Diff mode is not yet implemented.');

            default:
                throw new \RuntimeException(sprintf('Invalid mode provided. Allowed modes are: %s', implode(', ', [
                    self::modeForcePush(),
                    self::modeBranch(),
                    self::modeDiff(),
                ])));
        }

        $this->mode = $mode;
    }

    /**
     * Branch mode.
     *
     * @return string
     *   Branch mode name.
     */
    public static function modeBranch(): string
    {
        return 'branch';
    }

    /**
     * Force-push mode.
     *
     * @return string
     *   Force-push mode name.
     */
    public static function modeForcePush(): string
    {
        return 'force-push';
    }

    /**
     * Diff mode.
     *
     * @return string
     *   Diff mode name.
     */
    public static function modeDiff(): string
    {
        return 'diff';
    }

    /**
     * Resolve original branch to handle detached repositories.
     *
     * Usually, repository become detached when a tag is checked out.
     *
     * @param string $location
     *   Path to repository.
     *
     * @return null|string
     *   Branch or detachment source.
     *
     * @throws \Exception
     *   If neither branch nor detachment source is not found.
     */
    protected function resolveOriginalBranch(string $location): ?string
    {
        $branch = $this->gitGetCurrentBranch($location);

        // Repository could be in detached state. If this the case - we need to
        // capture the source of detachment, if exist.
        if ($branch === 'HEAD') {
            $branch = null;
            $result = $this->gitCommandRun($location, 'branch');
            $branchList = preg_split('/\R/', $result->getMessage());
            if ($branchList) {
                $branchList = array_filter($branchList);
                foreach ($branchList as $branch) {
                    if (preg_match('/\* \(.*detached .* ([^\)]+)\)/', $branch, $matches)) {
                        $branch = $matches[1];
                        break;
                    }
                }
            }
            if (empty($branch)) {
                throw new \Exception('Unable to determine detachment source');
            }
        }

        return $branch;
    }

    /**
     * Set the branch in the remote repository where commits will be pushed to.
     *
     * @param string $branch
     *   Branch in the remote repository.
     */
    protected function setDstBranch(string $branch): void
    {
        $branch = (string) $this->tokenProcess($branch);

        if (!self::gitIsValidBranch($branch)) {
            throw new \RuntimeException(sprintf('Incorrect value "%s" specified for git remote branch', $branch));
        }
        $this->dstBranch = $branch;
    }

    /**
     * Set commit message.
     *
     * @param string $message
     *   Commit message to set on the deployment commit.
     */
    protected function setMessage(string $message): void
    {
        $message = (string) $this->tokenProcess($message);
        $this->message = $message;
    }

    /**
     * Set replacement gitignore file path location.
     *
     * @param string $path
     *   Path to the replacement .gitignore file.
     *
     * @throws \Exception
     */
    protected function setGitignoreFile(string $path): void
    {
        $path = $this->fsGetAbsolutePath($path);
        $this->fsPathsExist($path);
        $this->gitignoreFile = $path;
    }

    /**
     * Check that there all requirements are met in order to to run this
     * command.
     */
    protected function checkRequirements(): void
    {
        // @todo: Refactor this into more generic implementation.
        $this->say('Checking requirements');
        if (!$this->fsIsCommandAvailable('git')) {
            throw new \RuntimeException('At least one of the script running requirements was not met');
        }
        $this->sayOkay('All requirements were met');
    }

    /**
     * Replace gitignore file with provided file.
     *
     * @param string $filename
     *   Path to new gitignore to replace current file with.
     * @param string $path
     *   Path to repository.
     */
    protected function replaceGitignore(string $filename, string $path): void
    {
        $this->printDebug('Replacing .gitignore: %s with %s', $path.DIRECTORY_SEPARATOR.'.gitignore', $filename);
        $this->fsFileSystem->copy($filename, $path.DIRECTORY_SEPARATOR.'.gitignore', true);
        $this->fsFileSystem->remove($filename);
    }

    /**
     * Helper to get a file name of the local exclude file.
     *
     * @param string $path
     *   Path to directory.
     *
     * @return string
     *   Exclude file name path.
     */
    protected function getLocalExcludeFileName(string $path): string
    {
        return $path.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'info'.DIRECTORY_SEPARATOR.'exclude';
    }

    /**
     * Check if local exclude (.git/info/exclude) file exists.
     *
     * @param string $path
     *   Path to repository.
     *
     * @return bool
     *   True if exists, false otherwise.
     */
    protected function localExcludeExists(string $path): bool
    {
        return $this->fsFileSystem->exists($this->getLocalExcludeFileName($path));
    }

    /**
     * Check if local exclude (.git/info/exclude) file is empty.
     *
     * @param string $path
     *   Path to repository.
     * @param bool $strict
     *   Flag to check if the file is empty. If false, comments and empty lines
     *   are considered as empty.
     *
     * @return bool
     *   - true, if $strict is true and file has no records.
     *   - false, if $strict is true and file has some records.
     *   - true, if $strict is false and file has only empty lines and comments.
     *   - false, if $strict is false and file lines other than empty lines or
     *     comments.
     *
     * @throws \Exception
     */
    protected function localExcludeEmpty(string $path, bool $strict = false): bool
    {
        if (!$this->localExcludeExists($path)) {
            throw new \Exception(sprintf('File "%s" does not exist', $path));
        }

        $filename = $this->getLocalExcludeFileName($path);
        if ($strict) {
            return empty(file_get_contents($filename));
        }
        $lines = file($filename);
        if ($lines) {
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines, static function ($line) : bool {
                return strlen($line) > 0;
            });
            $lines = array_filter($lines, static function ($line) : bool {
                return !str_starts_with(trim($line), '#');
            });
        }


        return empty($lines);
    }

    /**
     * Disable local exclude file (.git/info/exclude).
     *
     * @param string $path
     *   Path to repository.
     */
    protected function disableLocalExclude(string $path): void
    {
        $filename = $this->getLocalExcludeFileName($path);
        $filenameDisabled = $filename.'.bak';
        if ($this->fsFileSystem->exists($filename)) {
            $this->printDebug('Disabling local exclude');
            $this->fsFileSystem->rename($filename, $filenameDisabled);
        }
    }

    /**
     * Restore previously disabled local exclude file.
     *
     * @param string $path
     *   Path to repository.
     */
    protected function restoreLocalExclude(string $path): void
    {
        $filename = $this->getLocalExcludeFileName($path);
        $filenameDisabled = $filename.'.bak';
        if ($this->fsFileSystem->exists($filenameDisabled)) {
            $this->printDebug('Restoring local exclude');
            $this->fsFileSystem->rename($filenameDisabled, $filename);
        }
    }

    /**
     * Update index for all files.
     *
     * @param string $location
     *   Path to repository.
     *
     * @throws \Exception
     */
    protected function gitAddAll(string $location): void
    {
        $result = $this->gitCommandRun(
            $location,
            'add -A'
        );

        $this->printDebug(sprintf("Added all files:\n%s", $result->getMessage()));
    }

    /**
     * Update index for all files.
     *
     * @param string $location
     *   Path to repository.
     *
     * @throws \Exception
     */
    protected function gitUpdateIndex(string $location): void
    {
        $finder = new Finder();
        $files = $finder
            ->in($location)
            ->ignoreDotFiles(false)
            ->files();

        foreach ($files as $file) {
            $this->gitCommandRun(
                $location,
                sprintf('update-index --info-only --add "%s"', $file)
            );
            $this->printDebug(sprintf('Updated index for file "%s"', $file));
        }
    }

    /**
     * Remove ignored files.
     *
     * @param string $location
     *   Path to repository.
     * @param string|null $gitignorePath
     *   Gitignore file name.
     *
     * @throws \Exception
     *   If removal command finished with an error.
     */
    protected function removeIgnoredFiles(string $location, string $gitignorePath = null): void
    {
        $gitignorePath = $gitignorePath ? $gitignorePath : $location.DIRECTORY_SEPARATOR.'.gitignore';

        $gitignoreContent = file_get_contents($gitignorePath);
        if (!$gitignoreContent) {
            $this->printDebug('Unable to load '.$gitignoreContent);
        } else {
            $this->printDebug('-----.gitignore---------');
            $this->printDebug($gitignoreContent);
            $this->printDebug('-----.gitignore---------');
        }

        $command = sprintf('ls-files --directory -i -c --exclude-from=%s %s', $gitignorePath, $location);
        $result = $this->gitCommandRun($location, $command, 'Unable to remove ignored files', true);
        $files = preg_split('/\R/', $result->getMessage());
        if (!empty($files)) {
            $files = array_filter($files);
            foreach ($files as $file) {
                $fileName = $location.DIRECTORY_SEPARATOR.$file;
                $this->printDebug('Removing excluded file %s', $fileName);
                if ($this->fsFileSystem->exists($fileName)) {
                    $this->fsFileSystem->remove($fileName);
                }
            }
        }
    }

    /**
     * Remove 'other' files.
     *
     * 'Other' files are files that are neither staged nor tracked in git.
     *
     * @param string $location
     *   Path to repository.
     *
     * @throws \Exception
     *   If removal command finished with an error.
     */
    protected function removeOtherFiles(string $location): void
    {
        $command = 'ls-files --others --exclude-standard';
        $result = $this->gitCommandRun($location, $command, 'Unable to remove other files', true);
        $files = preg_split('/\R/', $result->getMessage());
        if (!empty($files)) {
            $files = array_filter($files);
            foreach ($files as $file) {
                $fileName = $location.DIRECTORY_SEPARATOR.$file;
                $this->printDebug('Removing other file %s', $fileName);
                $this->fsFileSystem->remove($fileName);
            }
        }
    }

    /**
     * Remove any repositories within current repository.
     *
     * @param string $path
     *   Path to current repository.
     */
    protected function removeSubRepos(string $path): void
    {
        $finder = new Finder();
        $dirs = $finder
            ->directories()
            ->name('.git')
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->depth('>0')
            ->in($path);

        $dirs = iterator_to_array($dirs->directories());

        foreach ($dirs as $dir) {
            if ($dir instanceof SplFileInfo) {
                $dir = $dir->getPathname();
            }
            $this->fsFileSystem->remove($dir);
            $this->printDebug('Removing sub-repository "%s"', (string) $dir);
        }
    }

    /**
     * Token callback to get current branch.
     *
     * @return string
     *   Branch name.
     *
     * @throws \Exception
     */
    protected function getTokenBranch(): string
    {
        return $this->gitGetCurrentBranch($this->src);
    }

    /**
     * Token callback to get tags.
     *
     * @param string|null $delimiter
     *   Token delimiter. Defaults to ', '.
     *
     * @return string
     *   String of tags.
     *
     * @throws \Exception
     */
    protected function getTokenTags(string $delimiter = null): string
    {
        $delimiter = $delimiter ? $delimiter : '-';
        $tags = $this->gitGetTags($this->src);

        return implode($delimiter, $tags);
    }

    /**
     * Token callback to get current timestamp.
     *
     * @param string $format
     *   Date format suitable for date() function.
     *
     * @return string
     *   Date string.
     */
    protected function getTokenTimestamp(string $format = 'Y-m-d_H-i-s'): string
    {
        return date($format, $this->now);
    }

    /**
     * Print success message.
     *
     * Usually used to explicitly state that some action was successfully
     * executed.
     *
     * @param string $text
     *   Message text.
     */
    protected function sayOkay(string $text): void
    {
        $color = 'green';
        $char = $this->decorationCharacter('V', '✔');
        $format = sprintf('<fg=white;bg=%s;options=bold>%%s %%s</fg=white;bg=%s;options=bold>', $color, $color);
        $this->writeln(sprintf($format, $char, $text));
    }

    /**
     * Print debug information.
     *
     * @param mixed ...$args
     *   The args.
     */
    protected function printDebug(mixed ...$args): void
    {
        if (!$this->debug) {
            return;
        }
        $message = array_shift($args);
        /* @phpstan-ignore-next-line */
        $this->writeln(vsprintf($message, $args));
    }
}
