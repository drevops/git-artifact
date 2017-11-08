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
     * Mode in which current build is going to run.
     *
     * Available modes: branch, force-push, diff.
     *
     * @var string
     */
    protected $mode;

    /**
     * Flag to specify if push is required or should be using dry run.
     *
     * @var string
     */
    protected $needsPush;

    /**
     * Internal option to set current timestamp.
     *
     * @var int
     */
    protected $now;

    /**
     * Path to report file.
     *
     * @var string
     */
    protected $report;

    /**
     * Artefact build result.
     *
     * @var bool
     */
    protected $result;

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
     * @option $push Push artefact to the remote repository. Defaults to FALSE.
     * @option $report Path to the report file.
     * @option $root Path to the root for file path resolution. If not
     *         specified, current directory is used.
     * @option $src Directory where source repository is located. If not
     *   specified, root directory is used.
     */
    public function artefact($remote, array $opts = [
        'branch' => InputOption::VALUE_REQUIRED,
        'gitignore' => InputOption::VALUE_REQUIRED,
        'message' => InputOption::VALUE_REQUIRED,
        'mode' => 'force-push',
        'now' => InputOption::VALUE_REQUIRED,
        'push' => false,
        'report' => InputOption::VALUE_REQUIRED,
        'root' => InputOption::VALUE_REQUIRED,
        'src' => InputOption::VALUE_REQUIRED,
    ])
    {
        $this->checkRequirements();
        $this->resolveOptions($opts);

        try {
            $this->gitSetRemoteRepo($remote);

            $this->showInfo();
            $this->prepareArtefact();

            if ($this->needsPush) {
                $this->doPush();
            } else {
                $this->yell('Cowardly refusing to push to remote. Use --push option to perform an actual push.');
            }

            if ($this->report) {
                $this->dumpReport();
            }
        } finally {
            $this->cleanup();
        }
    }

    protected function prepareArtefact()
    {
        if (!empty($this->gitignoreFile)) {
            $this->replaceGitignore($this->gitignoreFile, $this->gitGetSrcRepo());
        }

        $this->removeSubRepos($this->gitGetSrcRepo());

        $this->gitSwitchToNewBranch($this->gitGetSrcRepo(), $this->artefactBranch);

        $result = $this->gitCommit($this->gitGetSrcRepo(), $this->message);
        $this->say(sprintf('Added changes: %s', $result->getMessage()));
    }

    protected function cleanup()
    {
        $this->gitSwitchToBranch($this->gitGetSrcRepo(), $this->originalBranch);
        $this->gitRemoveBranch($this->gitGetSrcRepo(), $this->artefactBranch);
        $this->gitRemoveRemote($this->gitGetSrcRepo(), 'dst');
    }

    /**
     * Perform actual push to remote.
     */
    protected function doPush()
    {
        // @todo: Replace 'dst' with proper const.
        if (!$this->gitRemoteExists($this->gitGetSrcRepo(), 'dst')) {
            $this->gitAddRemote($this->gitGetSrcRepo(), 'dst', $this->gitGetRemoteRepo());
        }

        $result = $this->gitPush($this->gitGetSrcRepo(), $this->artefactBranch, 'dst', $this->dstBranch, $this->mode == self::modeForcePush());
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

        $this->fsSetRootDir($options['root']);

        // Default source to the root directory.
        $srcPath = !empty($options['src']) ? $this->fsGetAbsolutePath($options['src']) : $this->fsGetRootDir();
        $this->gitSetSrcRepo($srcPath);

        $this->originalBranch = $this->gitGetCurrentBranch($this->gitGetSrcRepo());
        $this->setDstBranch($options['branch']);
        $this->artefactBranch = $this->dstBranch.'-artefact';

        $message = !empty($options['message']) ? $options['message'] : self::getDefaultMessage();
        $this->setMessage($message);

        if (!empty($options['gitignore'])) {
            $this->setGitignoreFile($options['gitignore']);
        }

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
        $this->writeln(' Source repository:     '.$this->gitGetSrcRepo());
        $this->writeln(' Remote repository:     '.$this->gitGetRemoteRepo());
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
        $lines[] = ' Source repository: '.$this->gitGetSrcRepo();
        $lines[] = ' Remote repository: '.$this->gitGetRemoteRepo();
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
     *   CLI options to use as a context for mode validation.
     */
    protected function setMode($mode, array $options)
    {
        $this->say(sprintf('Running in "%s" mode', $mode));

        switch ($mode) {
            case self::modeForcePush():
                // Intentionally empty.
                break;

            case self::modeBranch():
                if ($options['branch'] == $this->gitGetCurrentBranch($this->gitGetSrcRepo())) {
                    throw new \RuntimeException('Invalid branch name for "branch" mode. Try adding a suffix to make the branch unique.');
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
        return $this->gitGetCurrentBranch($this->gitGetSrcRepo());
    }

    /**
     * Token callback to get tags.
     *
     * @param string $delimiter
     *   Optional token delimiter. Default to ', '.
     *
     * @return string
     */
    protected function getTokenTags($delimiter = ', ')
    {
        $tags = $this->gitGetTags($this->gitGetSrcRepo());

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
    protected function getTokenTimestamp($format)
    {
        return date($format, $this->now);
    }

    /**
     * Returns default remote branch.
     */
    protected static function getDefaultBranch()
    {
        return '[branch]-[timestamp:Y-m-d_H-i-s]';
    }

    /**
     * Returns default commit message.
     */
    protected static function getDefaultMessage()
    {
        return 'Deployment commit';
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
