<?php

declare(strict_types = 1);

namespace DrevOps\GitArtifact;

use Robo\Collection\CollectionBuilder;
use Robo\Contract\VerbosityThresholdInterface;
use Robo\Result;
use Robo\Task\Base\Exec;

/**
 * Trait GitTrait.
 */
trait GitTrait
{

    /**
     * Path to the source repository.
     *
     * @var string
     */
    protected $src;

    /**
     * Path to the destination repository.
     *
     * @var string
     */
    protected $dst;

    /**
     * Set source repository path with validation.
     *
     * @param string $path
     *   Path to repo.
     *
     * @throws \Exception
     */
    protected function gitSetSrcRepo(string $path): void
    {
        $this->fsPathsExist($path);
        $this->src = $path;
    }

    /**
     * Set remote path.
     *
     * @param string $path
     *   Path or URL of the remote git repository.
     *
     * @throws \Exception
     */
    protected function gitSetDst(string $path): void
    {
        if (!$this->gitIsRemote($path)) {
            throw new \RuntimeException(sprintf('Incorrect value "%s" specified for git remote', $path));
        }
        $this->dst = $this->gitIsRemote($path, 'local') ? $this->fsGetAbsolutePath($path) : $path;
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
    protected function gitAddRemote(string $location, string $name, string $remote): Result
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
     *
     * @throws \Exception
     */
    protected function gitRemoteExists(string $location, string $name): bool
    {
        $result = $this->gitCommandRun(
            $location,
            'remote',
            'Unable to list remotes',
            true
        );

        $lines = preg_split('/\R/', $result->getMessage());

        if (empty($lines)) {
            return false;
        }

        return in_array($name, $lines);
    }

    /**
     * Switch to new branch.
     *
     * @param string $location
     *   Local path or remote URI of the repository.
     * @param string $branch
     *   Branch name.
     * @param bool $createNew
     *   Optional flag to also create a branch before switching. Defaults to
     *   false.
     *
     * @return \Robo\Result
     *   Result object.
     *
     * @throws \Exception
     */
    protected function gitSwitchToBranch(string $location, string $branch, bool $createNew = false): Result
    {
        return $this->gitCommandRun(
            $location,
            sprintf('checkout %s %s', $createNew ? '-b' : '', $branch)
        );
    }

    /**
     * Remove git branch.
     *
     * @param string $location
     *   Local path or remote URI of the repository.
     * @param string $branch
     *   Branch name.
     *
     * @return \Robo\Result
     *   Result object.
     *
     * @throws \Exception
     */
    protected function gitRemoveBranch($location, $branch): Result
    {
        return $this->gitCommandRun(
            $location,
            sprintf('branch -D %s', $branch)
        );
    }

    /**
     * Removed git remote.
     *
     * @param string $location
     *   Local path or remote URI of the repository.
     * @param string $remote
     *   Remote name.
     *
     * @throws \Exception
     */
    protected function gitRemoveRemote(string $location, string $remote): void
    {
        if ($this->gitRemoteExists($location, $remote)) {
            $this->gitCommandRun(
                $location,
                sprintf('remote rm %s', $remote)
            );
        }
    }

    /**
     * Commit and push to specified remote.
     *
     * @param string $location
     *   Repository location path or URI.
     * @param string $localBranch
     *   Local branch name.
     * @param string $remoteName
     *   Remote name.
     * @param string $remoteBranch
     *   Remote branch to push to.
     * @param bool $force
     *   Force push.
     *
     * @return \Robo\Result
     *   Result object.
     *
     * @throws \Exception
     */
    protected function gitPush(string $location, string $localBranch, string $remoteName, string $remoteBranch, bool $force = false): Result
    {
        return $this->gitCommandRun(
            $location,
            sprintf('push %s refs/heads/%s:refs/heads/%s%s', $remoteName, $localBranch, $remoteBranch, $force ? ' --force' : ''),
            sprintf('Unable to push local branch "%s" to "%s" remote branch "%s"', $localBranch, $remoteName, $remoteBranch)
        );
    }

    /**
     * Commit all files to git repo.
     *
     * @param string $location
     *   Repository location path or URI.
     * @param string $message
     *   Commit message.
     *
     * @return \Robo\Result
     *   Result object.
     *
     * @throws \Exception
     */
    protected function gitCommit(string $location, string $message): Result
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
    protected function gitGetCurrentBranch(string $location): string
    {
        $result = $this->gitCommandRun(
            $location,
            'rev-parse --abbrev-ref HEAD',
            'Unable to get current repository branch',
            true
        );

        return trim($result->getMessage());
    }

    /**
     * Get all tags for the current commit.
     *
     * @param string $location
     *   Repository location path or URI.
     *
     * @return array<string>
     *   Array of tags.
     *
     * @throws \Exception
     *   If not able to retrieve tags.
     */
    protected function gitGetTags(string $location): array
    {
        $result = $this->gitCommandRun(
            $location,
            'tag -l --points-at HEAD',
            'Unable to retrieve tags',
            true
        );
        $tags = preg_split('/\R/', $result->getMessage());
        if (empty($tags)) {
            return [];
        }

        return array_filter($tags);
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
     * @param string|null $errorMessage
     *   Optional error message.
     * @param bool $noDebug
     *   Flag to enforce no-debug mode. Used by commands that use output for
     *   values.
     *
     * @return \Robo\Result
     *   Result object.
     *
     * @throws \Exception If command did not finish successfully.
     */
    protected function gitCommandRun(string $location, string $command, string $errorMessage = null, bool $noDebug = false): Result
    {
        $git = $this->gitCommand($location, $noDebug);
        /* @phpstan-ignore-next-line */
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
     * @param string|null $location
     *   Optional repository location.
     * @param bool $noDebug
     *   Flag to enforce no-debug mode. Used by commands that use output for
     *   values.
     *
     * @return \Robo\Task\Base\Exec|\Robo\Collection\CollectionBuilder
     *   Exec task.
     */
    protected function gitCommand(string $location = null, bool $noDebug = false): object
    {
        /* @phpstan-ignore-next-line */
        $git = $this->taskExec('git')
            ->printOutput(false)
            ->arg('--no-pager');

        if ($this->debug) {
            $git->env('GIT_SSH_COMMAND', 'ssh -vvv');
            if (!$noDebug) {
                $git->printOutput(true);
            }
            $git->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_NORMAL);
        } else {
            $git->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_DEBUG);
        }

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
     *
     * @throws \Exception
     */
    protected function gitIsRemote(string $location, string $type = 'any'): bool
    {
        $isLocal = $this->fsPathsExist($this->fsGetAbsolutePath($location), false);
        $isUri = self::gitIsUri($location);

        return match ($type) {
            'any' => $isLocal || $isUri,
            'local' => $isLocal,
            'uri' => $isUri,
            default => throw new \InvalidArgumentException(sprintf('Invalid argument "%s" provided', $type)),
        };
    }

    /**
     * Check if provided branch name can be used in git.
     *
     * @param string $branch
     *   Branch to check.
     *
     * @return bool
     *   TRUE if it is a valid Git branch, FALSE otherwise.
     */
    protected static function gitIsValidBranch(string $branch): bool
    {
        return preg_match('/^(?!\/|.*(?:[\/\.]\.|\/\/|\\|@\{))[^\040\177\s\~\^\:\?\*\[]+(?<!\.lock)(?<![\/\.])$/', $branch) && strlen($branch) < 255;
    }

    /**
     * Check if provided location is a URI.
     *
     * @param string $location
     *   Location to check.
     *
     * @return bool
     *   TRUE if location is URI, FALSE otherwise.
     */
    protected static function gitIsUri(string $location): bool
    {
        return (bool) preg_match('/^(?:git|ssh|https?|[\d\w\.\-_]+@[\w\.\-]+):(?:\/\/)?[\w\.@:\/~_-]+\.git(?:\/?|\#[\d\w\.\-_]+?)$/', $location);
    }
}
