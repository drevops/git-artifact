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
     * Destination branch with optional tokens.
     *
     * @var string
     */
    protected $branch;

    /**
     * Commit message with optional tokens.
     *
     * @var string
     */
    protected $message;

    /**
     * Gitignore file to be used during artefact creation.
     *
     * If not set, the current `.gitignore` will be used, if any.
     *
     * @var string
     */
    protected $gitignoreFile;

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
     * Flag to specify if force push is required.
     *
     * @var string
     */
    protected $force;

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
     * @option $root Path to the root for file path resolution. If not
     *         specified, current directory is used.
     * @option $src Directory where source repository is located. If not
     *   specified, root directory is used.
     * @option $branch Destination branch with optional tokens.
     * @option $message Commit message with optional tokens.
     * @option $gitignore Path to gitignore file to replace current .gitignore.
     * @option $push Push artefact to the remote repository. Defaults to FALSE.
     * @option $mirror Overwrite remote branch. Defaults to FALSE.
     */
    public function artefact($remote, array $opts = [
        'root' => InputOption::VALUE_REQUIRED,
        'src' => InputOption::VALUE_REQUIRED,
        'branch' => InputOption::VALUE_REQUIRED,
        'message' => InputOption::VALUE_REQUIRED,
        'gitignore' => InputOption::VALUE_REQUIRED,
        'push' => false,
        'now' => InputOption::VALUE_REQUIRED,
        'force' =>  false,
    ])
    {
        $this->checkRequirements();
        $this->resolveOptions($opts);

        $this->gitSetRemoteRepo($remote);

        $this->showInfo();

        if ($this->needsPush) {
            if ($this->force) {
                $this->doForcePush();
            } else {
                $this->doPush();
            }
        } else {
            $this->yell('Cowardly refusing to push to remote. Use --push option to perform an actual push.');
        }
    }

    /**
     * Perform common push steps.
     */
    protected function preparePush() {
        if (!$this->gitRemoteExists($this->gitGetSrcRepo(), 'dst')) {
            $this->gitAddRemote($this->gitGetSrcRepo(), 'dst', $this->gitGetRemoteRepo());
        }

        // Gitignore may not be provided in which case we just send send current
        // repo as is to remote.
        if (!empty($this->gitignoreFile)) {
            $this->replaceGitignore($this->gitignoreFile, $this->gitGetSrcRepo());
        }

        $this->removeSubRepos($this->gitGetSrcRepo());

        $currentBranch = $this->gitGetCurrentBranch($this->gitGetSrcRepo());
        // Switch to a new branch in current repo (for possible rollback),
        // but only if the deployment branch is different from current one.
        if ($this->branch != $currentBranch) {
            $this->gitCreateNewBranch($this->gitGetSrcRepo(), $this->branch);
        }

        $result = $this->gitCommit($this->gitGetSrcRepo(), $this->message);
        $this->say(sprintf('Added changes: %s', $result->getMessage()));
    }

    /**
     * Perform actual push to remote.
     */
    protected function doPush()
    {

        $result = $this->gitPush($this->gitGetSrcRepo(), 'dst', $this->branch);
        if ($result->wasSuccessful()) {
            $this->sayOkay(sprintf('Pushed branch "%s" with commit message "%s"', $this->branch, $this->message));
        } else {
            $this->say(sprintf('Error occurred while pushing branch "%s" with commit message "%s"', $this->branch, $this->message));
        }
    }

    /**
     * Perform force push to remote.
     */
    protected function doPushForce()
    {

        $result = $this->gitPush($this->gitGetSrcRepo(), 'dst', $this->branch, true);
        if ($result->wasSuccessful()) {
            $this->sayOkay(sprintf('Force pushed branch "%s" with commit message "%s"', $this->branch, $this->message));
        } else {
            $this->say(sprintf('Error occurred while force pushing branch "%s" with commit message "%s"', $this->branch, $this->message));
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

        $branch = !empty($options['branch']) ? $options['branch'] : self::getDefaultBranch();
        $branch = $this->tokenProcess($branch);
        $this->setBranch($branch);

        $message = !empty($options['message']) ? $options['message'] : self::getDefaultMessage();
        $message = $this->tokenProcess($message);
        $this->setMessage($message);

        if (!empty($options['gitignore'])) {
            $this->setGitignoreFile($options['gitignore']);
        }

        $this->needsPush = !empty($options['push']);

        $this->force = !empty($options['force']);
    }

    /**
     * Show artefact build information.
     */
    protected function showInfo()
    {
        $this->writeln('----------------------------------------------------------------------');
        $this->writeln(' Artefact information');
        $this->writeln('----------------------------------------------------------------------');
        $this->writeln(' Source repository:     '.$this->gitGetSrcRepo());
        $this->writeln(' Remote repository:     '.$this->gitGetRemoteRepo());
        $this->writeln(' Remote branch:         '.$this->branch);
        $this->writeln(' Gitignore file:        '.($this->gitignoreFile ? $this->gitignoreFile : 'No'));
        $this->writeln(' Will push:             '.($this->needsPush ? 'Yes' : 'No'));
        $this->writeln('----------------------------------------------------------------------');
    }

    /**
     * Set the branch of the remote repository.
     *
     * @param string $branch
     *   Branch of the remote repository.
     */
    protected function setBranch($branch)
    {
        if (!self::gitIsValidBranch($branch)) {
            throw new \RuntimeException(sprintf('Incorrect value "%s" specified for git remote branch', $branch));
        }
        $this->branch = $branch;
    }

    /**
     * Set commit message.
     *
     * @param string $message
     *   Commit message to set on the deployment commit.
     */
    protected function setMessage($message)
    {
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
     */
    protected function getTokenTags($delimiter)
    {
        $delimiter = empty($delimiter) ? ', ' : $delimiter;
        $tags = $this->gitGetTags($this->gitGetSrcRepo());

        return implode($delimiter, $tags);
    }

    /**
     * Token callback to get current timestamp.
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
