<?php

namespace IntegratedExperts\Robo;

use Symfony\Component\Console\Input\InputOption;

/**
 * Class Artefact.
 *
 * Robo task to package current repository with all dependencies and send it
 * to the remote repository.
 *
 * @package IntegratedExperts\Robo
 */
trait ArtefactTrait
{

    use FilesystemTrait {
        FilesystemTrait::__construct as private __artefactFsConstruct;
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
     * @var string
     */
    protected $originalBranch;

    /**
     * Destination branch with optional tokens.
     *
     * @var string
     */
    protected $dstBranch;

    /**
     * Local branch where artefact will be built.
     *
     * @var string
     */
    protected $artefactBranch;

    /**
     * Remote name.
     *
     * @var string
     */
    protected $remoteName;

    /**
     * Gitignore file to be used during artefact creation.
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
     * @var string
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
        $this->__artefactFsConstruct();
    }

    /**
     * Push artefact of current repository to remote git repository.
     *
     * @param string $remote
     *   Path to the remote git repository.
     * @param array  $opts
     *   Options.
     *
     * @option $branch Destination branch with optional tokens.
     * @option $gitignore Path to gitignore file to replace current .gitignore.
     * @option $message Commit message with optional tokens.
     * @option $mode Mode of artefact build: branch, force-push or diff.
     *   Defaults to force-push.
     * @option $now Internal value used to set internal time.
     * @option $no-cleanup Do not cleanup after run.
     * @option $push Push artefact to the remote repository. Defaults to FALSE.
     * @option $report Path to the report file.
     * @option $root Path to the root for file path resolution. If not
     *         specified, current directory is used.
     * @option $show-changes Show changes made to the repo by the build in the
     *         output.
     * @option $src Directory where source repository is located. If not
     *   specified, root directory is used.
     */
    public function artefact($remote, array $opts = [
        'branch' => '[branch]',
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
    ])
    {
        $this->checkRequirements();
        $this->resolveOptions($opts);

        try {
            $this->gitSetDst($remote);

            $this->showInfo();
            $this->prepareArtefact();

            if ($this->needsPush) {
                $this->doPush();
            } else {
                $this->yell('Cowardly refusing to push to remote. Use --push option to perform an actual push.');
            }
            $this->result = true;
        } finally {
            if ($this->report) {
                $this->dumpReport();
            }

            if ($this->needCleanup) {
                $this->cleanup();
            }

            $this->say('Deployment finished');
        }
    }

    /**
     * Prepare artefact to be then deployed.
     */
    protected function prepareArtefact()
    {
        $this->gitSwitchToBranch($this->src, $this->artefactBranch, true);

        if (!empty($this->gitignoreFile)) {
            $this->replaceGitignore($this->gitignoreFile, $this->src);
            $this->removeExcludedFiles($this->src);
        }

        $this->removeSubRepos($this->src);

        $result = $this->gitCommit($this->src, $this->message);

        if ($this->showChanges) {
            $this->say(sprintf('Added changes: %s', $result->getMessage()));
        }
    }

    /**
     * Cleanup after build.
     */
    protected function cleanup()
    {
        $this->gitSwitchToBranch($this->src, $this->originalBranch);
        $this->gitRemoveBranch($this->src, $this->artefactBranch);
        $this->gitRemoveRemote($this->src, $this->remoteName);
    }

    /**
     * Perform actual push to remote.
     */
    protected function doPush()
    {
        if (!$this->gitRemoteExists($this->src, $this->remoteName)) {
            $this->gitAddRemote($this->src, $this->remoteName, $this->dst);
        }

        $result = $this->gitPush($this->src, $this->artefactBranch, $this->remoteName, $this->dstBranch, $this->mode == self::modeForcePush());
        $this->result = $result->wasSuccessful();
        if ($this->result) {
            $this->sayOkay(sprintf('Pushed branch "%s" with commit message "%s"', $this->dstBranch, $this->message));
        } else {
            throw new \Exception(sprintf('Error occurred while pushing branch "%s" with commit message "%s"', $this->dstBranch, $this->message));
        }
    }

    /**
     * Resolve and validate CLI options values into internal values.
     *
     * @param array $options
     *   Array of CLI options.
     */
    protected function resolveOptions(array $options)
    {
        $this->now = !empty($options['now']) ? $options['now'] : time();

        $this->remoteName = 'dst';

        $this->fsSetRootDir($options['root']);

        // Default source to the root directory.
        $srcPath = !empty($options['src']) ? $this->fsGetAbsolutePath($options['src']) : $this->fsGetRootDir();
        $this->gitSetSrcRepo($srcPath);

        $this->originalBranch = $this->resolveOriginalBranch($this->src);
        $this->setDstBranch($options['branch']);
        $this->artefactBranch = $this->dstBranch.'-artefact';

        $this->setMessage($options['message']);

        if (!empty($options['gitignore'])) {
            $this->setGitignoreFile($options['gitignore']);
        }

        $this->showChanges = !empty($options['show-changes']);

        $this->needCleanup = empty($options['no-cleanup']);

        $this->needsPush = !empty($options['push']);

        $this->report = !empty($options['report']) ? $options['report'] : null;

        $this->setMode($options['mode'], $options);
    }

    /**
     * Show artefact build information.
     */
    protected function showInfo()
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
     * Dump artefact report to a file.
     */
    protected function dumpReport()
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
     * @param array  $options
     *   Array of CLI options.
     */
    protected function setMode($mode, array $options)
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
                break;

            default:
                throw new \RuntimeException(sprintf('Invalid mode provided. Allowed modes are: %s'), implode(', ', [
                    self::modeForcePush(),
                    self::modeBranch(),
                    self::modeDiff(),
                ]));
        }

        $this->mode = $mode;
    }

    /**
     * Branch mode.
     *
     * @return string
     *   Branch mode name.
     */
    public static function modeBranch()
    {
        return 'branch';
    }

    /**
     * Force-push mode.
     *
     * @return string
     *   Force-push mode name.
     */
    public static function modeForcePush()
    {
        return 'force-push';
    }

    /**
     * Diff mode.
     *
     * @return string
     *   Diff mode name.
     */
    public static function modeDiff()
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
     * @throws \Exception
     *   If neither branch nor detachment source is not found.
     */
    protected function resolveOriginalBranch($location)
    {
        $branch = $this->gitGetCurrentBranch($location);

        // Repository could be in detached state. If this the case - we need to
        // capture the source of detachment, if exist.
        if ($branch == 'HEAD') {
            $branch = null;
            $result = $this->gitCommandRun($location, 'branch');
            $branchList = array_filter(preg_split('/\R/', $result->getMessage()));

            foreach ($branchList as $branch) {
                if (preg_match('/\* \(.*detached .* ([^\)]+)\)/', $branch, $matches)) {
                    $branch = $matches[1];
                    break;
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
    protected function setDstBranch($branch)
    {
        $branch = $this->tokenProcess($branch);

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
    protected function setMessage($message)
    {
        $message = $this->tokenProcess($message);
        $this->message = $message;
    }

    /**
     * Set replacement gitignore file path location.
     *
     * @param string $path
     *   Path to the replacement .gitignore file.
     */
    protected function setGitignoreFile($path)
    {
        $path = $this->fsGetAbsolutePath($path);
        $this->fsPathsExist($path);
        $this->gitignoreFile = $path;
    }

    /**
     * Check that there all requirements are met in order to to run this
     * command.
     */
    protected function checkRequirements()
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
    protected function replaceGitignore($filename, $path)
    {
        $this->fsFileSystem->copy($filename, $path.DIRECTORY_SEPARATOR.'.gitignore', true);
        $this->fsFileSystem->remove($filename);
    }

    /**
     * Remove excluded files.
     *
     * @param string $location
     *   Path to repository.
     * @param string $gitignore
     *   Gitignore file name.
     *
     * @throws \Exception
     *   If removal command finished with an error.
     */
    protected function removeExcludedFiles($location, $gitignore = '.gitignore')
    {
        $command = sprintf('ls-files --directory --i --exclude-from=%s %s', $location.DIRECTORY_SEPARATOR.$gitignore, $location);
        $result = $this->gitCommandRun($location, $command, 'Unable to remove excluded files');
        $excludedFiles = array_filter(preg_split('/\R/', $result->getMessage()));
        foreach ($excludedFiles as $excludedFile) {
            $this->fsFileSystem->remove($location.DIRECTORY_SEPARATOR.$excludedFile);
        }
    }

    /**
     * Remove any repositories within current repository.
     *
     * @param string $path
     *   Path to current repository.
     */
    protected function removeSubRepos($path)
    {
        $dirs = $this->fsFinder
            ->in($path)
            ->name('/\.git$/')
            ->ignoreDotFiles(false)
            ->ignoreVCS(false)
            ->notPath('vendor')
            ->depth('>1');

        $this->fsFileSystem->remove($dirs);
    }

    /**
     * Token callback to get current branch.
     */
    protected function getTokenBranch()
    {
        return $this->gitGetCurrentBranch($this->src);
    }

    /**
     * Token callback to get tags.
     *
     * @param string $delimiter
     *   Token delimiter. Defaults to ', '.
     *
     * @return string
     */
    protected function getTokenTags($delimiter)
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
     * @return false|string
     */
    protected function getTokenTimestamp($format = 'Y-m-d_H-i-s')
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
    protected function sayOkay($text)
    {
        $color = 'green';
        $char = $this->decorationCharacter('V', '✔');
        $format = "<fg=white;bg=$color;options=bold>%s %s</fg=white;bg=$color;options=bold>";
        $this->writeln(sprintf($format, $char, $text));
    }
}
