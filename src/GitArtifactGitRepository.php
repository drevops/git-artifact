<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact;

use CzProject\GitPhp\GitRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Artifact git repository.
 */
class GitArtifactGitRepository extends GitRepository {

  /**
   * Filesystem.
   */
  protected Filesystem $fileSystem;

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Switch to new branch.
   *
   * @param string $branchName
   *   Branch name.
   * @param bool $createNew
   *   Optional flag to also create a branch before switching. Default false.
   *
   * @return GitArtifactGitRepository
   *   The git repository.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function switchToBranch(string $branchName, bool $createNew = FALSE): GitArtifactGitRepository {
    if (!$createNew) {
      return $this->checkout($branchName);
    }

    return $this->createBranch($branchName, TRUE);
  }

  /**
   * Remove branch.
   *
   * @param string $name
   *   Branch name.
   * @param bool $force
   *   Force remove or not.
   *
   * @return GitArtifactGitRepository
   *   Git repository
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function removeBranch($name, bool $force = FALSE): GitArtifactGitRepository {
    if (empty($name)) {
      return $this;
    }

    if (!$force) {
      return parent::removeBranch($name);
    }

    $this->run('branch', ['-D' => $name]);

    return $this;
  }

  /**
   * Commit all files to git repo.
   *
   * @param string $message
   *   The commit message.
   *
   * @return array<string>
   *   The changes.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function commitAllChanges(string $message): array {
    $this->addAllChanges();

    // We do not use commit method because we need return the output.
    return $this->execute('commit', '--allow-empty', [
      '-m' => $message,
    ]);
  }

  /**
   * Git ls-files command.
   *
   * @param array<mixed> $options
   *   Options.
   * @param array<string> $files
   *   Files to show.
   *
   * @return string[]|null
   *   Files.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function lsFiles(array $options = [], array $files = []): ?array {
    $commandArgs = array_merge(['ls-files', $options, '--end-of-options'], $files);
    return $this->extractFromCommand($commandArgs);
  }

  /**
   * Remove remote by name.
   *
   * We need override this method because parent method does not work.
   *
   * @param string $name
   *   Remote name.
   *
   * @return GitArtifactGitRepository
   *   Git repo.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function removeRemote($name): GitArtifactGitRepository {
    if ($this->isRemoteExists($name)) {
      $this->run('remote', 'remove', $name);
    }

    return $this;
  }

  /**
   * Get remote list.
   *
   * @return array<string>|null
   *   Remotes.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function getRemotes(): ?array {
    return $this->extractFromCommand(['remote']);
  }

  /**
   * Check remote is existing or not by remote name.
   *
   * @param string $remoteName
   *   Remote name to check.
   *
   * @return bool
   *   Exist or not.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  public function isRemoteExists(string $remoteName): bool {
    $remotes = $this->getRemotes();
    if (empty($remotes)) {
      return FALSE;
    }

    return in_array($remoteName, $remotes);
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
  public static function isUri(string $location): bool {
    return (bool) preg_match('/^(?:git|ssh|https?|[\d\w\.\-_]+@[\w\.\-]+):(?:\/\/)?[\w\.@:\/~_-]+\.git(?:\/?|\#[\d\w\.\-_]+?)$/', $location);
  }

  /**
   * Check if provided branch name can be used in git.
   *
   * @param string $branchName
   *   Branch to check.
   *
   * @return bool
   *   TRUE if it is a valid Git branch, FALSE otherwise.
   */
  public static function isValidBranchName(string $branchName): bool {
    return preg_match('/^(?!\/|.*(?:[\/\.]\.|\/\/|\\|@\{))[^\040\177\s\~\^\:\?\*\[]+(?<!\.lock)(?<![\/\.])$/', $branchName) && strlen($branchName) < 255;
  }

  /**
   * Check if provided remote url is local path or remote URI.
   *
   * @param string $pathOrUri
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
  public static function isValidRemoteUrl(string $pathOrUri, string $type = 'any'): bool {
    $filesystem = new Filesystem();
    $isLocal = $filesystem->exists($pathOrUri);
    $isUri = self::isUri($pathOrUri);

    return match ($type) {
      'any' => $isLocal || $isUri,
      'local' => $isLocal,
      'uri' => $isUri,
      default => throw new \InvalidArgumentException(sprintf('Invalid argument "%s" provided', $type)),
    };
  }

}
