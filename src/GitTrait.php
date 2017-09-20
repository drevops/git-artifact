<?php

namespace IntegratedExperts\Robo;

/**
 * Trait GitTrait.
 *
 * @package IntegratedExperts\Robo
 */
trait GitTrait
{

    /**
     * Path to the source repository.
     *
     * @var string
     */
    protected $gitSrcRepo;

    /**
     * Path to the destination repository.
     *
     * @var string
     */
    protected $gitRemoteRepo;

    /**
     * Get source repository location.
     *
     * @return string
     *   Source repository location.
     */
    protected function gitGetSrcRepo()
    {
        return $this->gitSrcRepo;
    }

    /**
     * Set source repository location with validation.
     */
    protected function gitSetSrcRepo($path)
    {
        $this->fsPathsExist($path);
        $this->gitSrcRepo = $path;
    }

    /**
     * Get remote repository location.
     *
     * @return string
     *   Remote repository location.
     */
    protected function gitGetRemoteRepo()
    {
        return $this->gitRemoteRepo;
    }

    /**
     * Set git remote location.
     *
     * @param string $location
     *   Path or URL of the remote git repository.
     */
    protected function gitSetRemoteRepo($location)
    {
        if (!$this->gitIsRemote($location)) {
            throw new \RuntimeException(sprintf('Incorrect value "%s" specified for git remote', $location));
        }
        $this->gitRemoteRepo = $this->gitIsRemote($location, 'local') ? $this->fsGetAbsolutePath($location) : $location;
    }

    /**
     * Add remote for specified repo.
     *
     * @param string $location
     *   Local path or remote URI of the repository to add remote for.
     * @param string $name
     *   Remote name.
     * @param string $remote
     *   Path or remote URI of the repository to add remote for.
     *
     * @return \Robo\Result
     *   Result object.
     *
     * @throws \Exception
     *   If unable to add a remote.
     */
    protected function gitAddRemote($location, $name, $remote)
    {
        return $this->gitCommandRun(
            $location,
            sprintf('remote add %s %s', $name, $remote),
            sprintf('Unable to add "%s" remote', $name)
        );
    }

    /**
     * Check if specified remote already exists in current repo.
     *
     * @param string $location
     *   Local path or remote URI of the repository to add remote for.
     * @param string $name
     *   Remote name.
     *
     * @return bool
     *   TRUE if remote with the name already exists in current repo, FALSE
     *   otherwise.
     */
    protected function gitRemoteExists($location, $name)
    {
        $result = $this->gitCommandRun(
            $location,
            sprintf('remote'),
            'Unable to list remotes'
        );

        $lines = preg_split('/\R/', $result->getMessage());

        return in_array($name, $lines);
    }

    protected function gitCreateNewBranch($location, $branch)
    {
        return $this->gitCommandRun(
            $location,
            sprintf('checkout -b %s', $branch)
        );
    }

    /**
     * Commit and push to specified remote.
     *
     * @param string $location
     *   Repository location path or URI.
     * @param string $remoteName
     *   Remote name.
     * @param string $remoteBranch
     *   Remote branch to push to.
     * @param string $message
     *   Commit message.
     *
     * @return \Robo\Result
     *   Result object.
     */
    protected function gitPush($location, $remoteName, $remoteBranch)
    {
        return $this->gitCommandRun(
            $location,
            sprintf('push %s refs/heads/%2$s:refs/heads/%2$s', $remoteName, $remoteBranch),
            sprintf('Unable to push to "%s" remote', $remoteName)
        );
    }

    protected function gitCommit($location, $message)
    {
        $this->gitCommandRun(
            $location,
            'add -A'
        );

        return $this->gitCommandRun(
            $location,
            sprintf('commit --allow-empty -m "%s"', $message)
        );
    }

    /**
     * Get current branch.
     *
     * @param string $location
     *   Repository location path or URI.
     *
     * @return string
     *   Current branch.
     * @throws \Exception
     *  If unable to get the branch.
     */
    protected function gitGetCurrentBranch($location)
    {
        $result = $this->gitCommandRun(
            $location,
            'rev-parse --abbrev-ref HEAD',
            'Unable to get current repository branch'
        );

        return trim($result->getMessage());
    }

    /**
     * Get all tags for the current commit.
     *
     * @param string $location
     *   Repository location path or URI.
     *
     * @return array
     *   Array of tags.
     * @throws \Exception
     *   If not able to retrieve tags.
     */
    protected function gitGetTags($location)
    {
        $result = $this->gitCommandRun(
            $location,
            'tag -l --points-at HEAD',
            'Unable to retrieve tags'
        );

        return array_filter(preg_split('/\R/', $result->getMessage()));
    }

    /**
     * Run git command.
     *
     * We cannot use Robo's git stack here as it does not support specifying
     * current git working dir.
     * Instead, we are using Robo's exec stack and our own wrapper.
     *
     * @param string $location
     *   Repository location path or URI.
     * @param string $command
     *   Command to run.
     * @param null   $errorMessage
     *   Optional error message.
     *
     * @return \Robo\Result
     *   Result object.
     * @throws \Exception If command did not finish successfully.
     */
    protected function gitCommandRun($location, $command, $errorMessage = null)
    {
        $git = $this->gitCommand($location);
        $git->rawArg($command);
        $result = $git->run();

        if (!$result->wasSuccessful()) {
            $message = $errorMessage ? sprintf('%s: %s', $errorMessage, $result->getMessage()) : $result->getMessage();
            throw new \Exception($message);
        }

        return $result;
    }

    /**
     * Get unified git command.
     *
     * @param string $location
     *   Optional repository location.
     *
     * @return \Robo\Task\Base\Exec
     *   Exect task.
     */
    protected function gitCommand($location = null)
    {
        $git = $this->taskExec('git');
        $git->arg('--no-pager');
        if (!empty($location)) {
            $git->arg('--git-dir='.$location.'/.git');
            $git->arg('--work-tree='.$location);
        }

        return $git;
    }

    /**
     * Check if provided location is local path or remote URI.
     *
     * @param string $location
     *   Local path or remote URI.
     * @param string $type
     *   One of the predefined types:
     *   - any: Expected to have either local path or remote URI provided.
     *   - local: Expected to have local path provided.
     *   - uri: Expected to have remote URI provided.
     *
     * @return bool
     *   Returns TRUE if location matches type, FALSE otherwise.
     */
    protected function gitIsRemote($location, $type = 'any')
    {
        $isLocal = $this->fsPathsExist($this->fsGetAbsolutePath($location), false);
        $isUri = self::gitIsUri($location);

        switch ($type) {
            case 'any':
                return $isLocal || $isUri;

            case 'local':
                return $isLocal;

            case 'uri':
                return $isUri;

            default:
                throw new \InvalidArgumentException(sprintf('Invalid argument "%s" provided', $type));
        }
    }

    /**
     * Check if provided branch name can be used in git.
     */
    protected static function gitIsValidBranch($branch)
    {
        return (bool) preg_match('/^(?!\/|.*(?:[\/\.]\.|\/\/|\\|@\{))[^\040\177\s\~\^\:\?\*\[]+(?<!\.lock)(?<![\/\.])$/', $branch) && strlen($branch) < 255;
    }

    /**
     * Check if provided location is a URI.
     */
    protected static function gitIsUri($location)
    {
        return (bool) preg_match('/^(?:git|ssh|https?|[\d\w\.\-_]+@[\w\.\-]+):(?:\/\/)?[\w\.@:\/~_-]+\.git(?:\/?|\#[\d\w\.\-_]+?)$/', $location);
    }
}
