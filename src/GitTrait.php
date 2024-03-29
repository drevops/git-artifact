<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact;

use GitWrapper\GitCommand;
use GitWrapper\GitWrapper;

/**
 * Trait GitTrait.
 */
trait GitTrait {

  use FilesystemTrait;

  /**
   * Git Wrapper.
   */
  protected GitWrapper $gitWrapper;

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
  protected function gitSetSrcRepo(string $path): void {
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
  protected function gitSetDst(string $path): void {
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
   * @return string
   *   stdout.
   *
   * @throws \Exception
   *   If unable to add a remote.
   */
  protected function gitAddRemote(string $location, string $name, string $remote): string {
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
  protected function gitRemoteExists(string $location, string $name): bool {
    $result = $this->gitCommandRun(
          $location,
          'remote',
          'Unable to list remotes',
      );

    $lines = preg_split('/\R/', (string) $result);

    if (empty($lines)) {
      return FALSE;
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
   * @return string
   *   stdout.
   *
   * @throws \Exception
   */
  protected function gitSwitchToBranch(string $location, string $branch, bool $createNew = FALSE): string {
    $command = $createNew ? sprintf('checkout -b %s', $branch) : sprintf('checkout %s', $branch);

    return $this->gitCommandRun(
          $location,
          $command,
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
   * @return string
   *   stdout.
   *
   * @throws \Exception
   */
  protected function gitRemoveBranch($location, $branch): string {
    return $this->gitCommandRun(
          $location,
          sprintf('branch -D %s', $branch),
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
  protected function gitRemoveRemote(string $location, string $remote): void {
    if ($this->gitRemoteExists($location, $remote)) {
      $this->gitCommandRun(
            $location,
            sprintf('remote rm %s', $remote),
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
   * @return string
   *   Stdout.
   *
   * @throws \Exception
   */
  protected function gitPush(
        string $location,
        string $localBranch,
        string $remoteName,
        string $remoteBranch,
        bool $force = FALSE
    ): string {
    return $this->gitCommandRun(
          $location,
          sprintf(
              'push %s refs/heads/%s:refs/heads/%s%s',
              $remoteName,
              $localBranch,
              $remoteBranch,
              $force ? ' --force' : ''
          ),
          sprintf(
              'Unable to push local branch "%s" to "%s" remote branch "%s"',
              $localBranch,
              $remoteName,
              $remoteBranch
          )
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
   * @return string
   *   Stdout.
   *
   * @throws \Exception
   */
  protected function gitCommit(string $location, string $message): string {
    $this->gitCommandRun(
          $location,
          'add -A',
      );
    $command = new GitCommand('commit', [
      'allow-empty' => TRUE,
      'm' => $message,
    ]);
    $command->setDirectory($location);

    return $this->gitWrapper->run($command);
  }

  /**
   * Get current branch.
   *
   * @param string $location
   *   Repository location path or URI.
   *
   * @return string
   *   Current branch.
   *
   * @throws \Exception
   *   If unable to get the branch.
   */
  protected function gitGetCurrentBranch(string $location): string {
    $result = $this->gitCommandRun(
          $location,
          'rev-parse --abbrev-ref HEAD',
          'Unable to get current repository branch',
      );

    return trim((string) $result);
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
  protected function gitGetTags(string $location): array {
    $result = $this->gitCommandRun(
          $location,
          'tag -l --points-at HEAD',
          'Unable to retrieve tags',
      );
    $tags = preg_split('/\R/', (string) $result);
    if (empty($tags)) {
      return [];
    }

    return array_filter($tags);
  }

  /**
   * Run git command.
   *
   * @param string $location
   *   Repository location path or URI.
   * @param string $command
   *   Command to run.
   * @param string $errorMessage
   *   Optional error message.
   *
   * @return string
   *   Stdout.
   *
   * @throws \Exception
   *   If command did not finish successfully.
   */
  protected function gitCommandRun(
        string $location,
        string $command,
        string $errorMessage = '',
    ): string {
    $command = '--no-pager ' . $command;
    try {
      return $this->gitWrapper->git($command, $location);
    }
    catch (\Exception $exception) {
      if ($errorMessage !== '') {
        throw new \Exception($errorMessage, $exception->getCode(), $exception);
      }
      throw $exception;
    }
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
  protected function gitIsRemote(string $location, string $type = 'any'): bool {
    $isLocal = $this->fsPathsExist($this->fsGetAbsolutePath($location), FALSE);
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
  protected static function gitIsValidBranch(string $branch): bool {
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
  protected static function gitIsUri(string $location): bool {
    return (bool) preg_match('/^(?:git|ssh|https?|[\d\w\.\-_]+@[\w\.\-]+):(?:\/\/)?[\w\.@:\/~_-]+\.git(?:\/?|\#[\d\w\.\-_]+?)$/', $location);
  }

}
