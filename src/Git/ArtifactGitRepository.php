<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Git;

use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\IRunner;
use CzProject\GitPhp\RunnerResult;
use DrevOps\GitArtifact\Traits\FilesystemTrait;
use DrevOps\GitArtifact\Traits\LoggerTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Artifact git repository.
 */
class ArtifactGitRepository extends GitRepository {

  use FilesystemTrait;
  use LoggerTrait;

  /**
   * The gitignore file path.
   */
  protected string $gitignoreFile;

  /**
   * {@inheritdoc}
   */
  public function __construct($repository, ?IRunner $runner = NULL, ?LoggerInterface $logger = NULL) {
    parent::__construct($repository, $runner);

    $this->fs = new Filesystem();

    if ($logger instanceof LoggerInterface) {
      $this->logger = $logger;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function run(...$args): RunnerResult {
    $command = array_shift($args);
    array_unshift($args, '--no-pager', $command);

    return parent::run(...$args);
  }

  /**
   * Set gitignore file.
   */
  public function setGitignoreFile(string $filename): static {
    $this->gitignoreFile = $filename;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addRemote($name, $url, ?array $options = NULL): static {
    if (!self::isValidRemote($url)) {
      throw new \InvalidArgumentException(sprintf('Invalid remote URL provided: %s', $url));
    }

    return parent::addRemote($name, $url, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function removeRemote($name): static {
    if (in_array($name, $this->listRemotes())) {
      $this->run('remote', 'remove', $name);
    }

    return $this;
  }

  /**
   * Switch to new branch.
   *
   * @param string $branch
   *   Branch name.
   * @param bool $create_new
   *   Optional flag to also create a branch before switching. Default false.
   *
   * @return static
   *   The git repository.
   */
  public function switchToBranch(string $branch, bool $create_new = FALSE): static {
    if (!$create_new) {
      return $this->checkout($branch);
    }

    return $this->createBranch($branch, TRUE);
  }

  /**
   * Remove branch.
   *
   * @param string $name
   *   Branch name.
   * @param bool $force
   *   Force remove or not.
   *
   * @return static
   *   Git repository
   */
  public function removeBranch($name, bool $force = FALSE): static {
    if (empty($name)) {
      return $this;
    }

    $branches = $this->getBranches();

    if (empty($branches)) {
      return $this;
    }

    if (!in_array($name, $branches)) {
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
   */
  public function commitAllChanges(string $message): array {
    $this->addAllChanges();

    // We do not use the commit method because we need return the output.
    return $this->execute('commit', '--allow-empty', [
      '-m' => $message,
    ]);
  }

  /**
   * Disable local exclude file (.git/info/exclude).
   */
  public function disableLocalExclude(): static {
    $filename = $this->getRepositoryPath() . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'info' . DIRECTORY_SEPARATOR . 'exclude';

    if ($this->fs->exists($filename)) {
      $this->logger->debug('Disabling local exclude');
      $this->fs->rename($filename, $filename . '.bak');
    }

    return $this;
  }

  /**
   * Restore previously disabled local exclude file.
   */
  public function restoreLocalExclude(): static {
    $filename = $this->getRepositoryPath() . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'info' . DIRECTORY_SEPARATOR . 'exclude';

    if ($this->fs->exists($filename . '.bak')) {
      $this->logger->debug('Restoring local exclude');
      $this->fs->rename($filename . '.bak', $filename);
    }

    return $this;
  }

  /**
   * List remotes.
   *
   * @return array<string>
   *   Remotes.
   */
  protected function listRemotes(): array {
    return $this->extractFromCommand(['remote']) ?: [];
  }

  /**
   * Get tag pointing to HEAD.
   *
   * @return string[]
   *   Array of tags from the latest commit.
   *
   * @throws \Exception
   *   If no tags found in the latest commit.
   */
  public function listTagsPointingToHead(): array {
    $tags = $this->extractFromCommand(['tag', ['--points-at', 'HEAD']]);

    if (empty($tags)) {
      throw new \Exception('No tags found in the latest commit.');
    }

    return $tags;
  }

  /**
   * Ger original branch, accounting for detached repository state.
   *
   * Usually, repository become detached when a tag is checked out.
   *
   * @return string
   *   Branch or detachment source.
   *
   * @throws \Exception
   *   If neither branch nor detachment source is not found.
   */
  public function getOriginalBranch(): string {
    $branch = $this->getCurrentBranchName();

    // Repository could be in detached state. If this the case - we need to
    // capture the source of detachment, if it exists.
    if (str_contains($branch, 'HEAD detached')) {
      $branch = NULL;
      $branch_list = $this->getBranches();
      if ($branch_list) {
        $branch_list = array_filter($branch_list);
        foreach ($branch_list as $branch) {
          if (preg_match('/\(.*detached .* ([^)]+)\)/', $branch, $matches)) {
            $branch = $matches[1];
            break;
          }
        }
      }

      if (empty($branch)) {
        throw new \Exception('Unable to determine a detachment source');
      }
    }

    return $branch;
  }

  /**
   * Remove ignored files.
   */
  public function removeIgnoredFiles(): static {
    if (!empty($this->gitignoreFile)) {
      $gitignore = $this->getRepositoryPath() . DIRECTORY_SEPARATOR . '.gitignore';
      $this->logger->debug(sprintf('Copying custom .gitignore file from %s to %s', $this->gitignoreFile, $gitignore));
      $this->fs->copy($this->gitignoreFile, $gitignore, TRUE);

      // Remove custom .gitignore file if it is within the repository.
      // @todo Review if this is "magic" and should be explicitly listed in
      // the file itself. Alternatively, we could add a check if the custom
      // gitignore is ignored within itself and add it if it is not.
      if (str_starts_with($this->gitignoreFile, $this->getRepositoryPath())) {
        $this->fs->remove($this->gitignoreFile);
      }

      // Custom .gitignore may contain rules that will change the list of
      // ignored files. We need to add these files as changes so that they
      // could be reported as excluded by the command below.
      $this->addAllChanges();

      $files = $this->extractFromCommand(['ls-files', '-i', '-c', '--exclude-from=' . $gitignore]) ?: [];
      $files = array_filter($files);

      foreach ($files as $file) {
        $filename = $this->getRepositoryPath() . DIRECTORY_SEPARATOR . $file;
        if ($this->fs->exists($filename)) {
          $this->logger->debug(sprintf('Removing ignored file %s', $filename));
          $this->fs->remove($filename);
        }
      }
    }

    return $this;
  }

  /**
   * Remove 'other' files.
   *
   * 'Other' files are files that are neither staged nor tracked in git.
   */
  public function removeOtherFiles(): static {
    $files = $this->extractFromCommand(['ls-files', '--other', '--exclude-standard']) ?: [];
    $files = array_filter($files);

    foreach ($files as $file) {
      $filename = $this->getRepositoryPath() . DIRECTORY_SEPARATOR . $file;
      if ($this->fs->exists($filename)) {
        $this->logger->debug(sprintf('Removing other file %s', $filename));
        $this->fs->remove($filename);
      }
    }

    return $this;
  }

  /**
   * Remove any repositories within current repository.
   */
  public function removeSubRepositories(): static {
    $finder = new Finder();
    $dirs = $finder
      ->directories()
      ->name('.git')
      ->ignoreDotFiles(FALSE)
      ->ignoreVCS(FALSE)
      ->depth('>0')
      ->in($this->getRepositoryPath());

    $dirs = iterator_to_array($dirs->directories());

    foreach ($dirs as $dir) {
      $dir = $dir->getPathname();
      $this->fs->remove($dir);
      $this->logger->debug(sprintf('Removing sub-repository "%s"', $this->fsGetAbsolutePath((string) $dir)));
    }

    // After removing sub-repositories, the files that were previously tracked
    // in those repositories are now become a part of the current repository.
    // We need to add them as changes.
    $this->addAllChanges();

    return $this;
  }

  /**
   * Check if provided branch name can be used in Git.
   *
   * @param string $name
   *   Branch name to check.
   *
   * @return bool
   *   TRUE if it is a valid Git branch, FALSE otherwise.
   */
  public static function isValidBranchName(string $name): bool {
    return preg_match('/^(?!\/|.*(?:[\/\.]\.|\/\/|\\|@\{))[^\040\177\s\~\^\:\?\*\[]+(?<!\.lock)(?<![\/\.])$/', $name) && strlen($name) < 255;
  }

  /**
   * Check if provided remote url is local path or remote URI.
   *
   * @param string $uri
   *   Local path or remote URL.
   * @param string $type
   *   One of the predefined types:
   *   - any: Expected to have either local path or remote URI provided.
   *   - local: Expected to have a local path provided.
   *   - external: Expected to have an external URI provided.
   *
   * @return bool
   *   Returns TRUE if location matches type, FALSE otherwise.
   *
   * @throws \Exception
   */
  public static function isValidRemote(string $uri, string $type = 'any'): bool {
    $filesystem = new Filesystem();

    $is_local = $filesystem->exists($uri);
    $is_external = (bool) preg_match('/^(?:git|ssh|https?|[\d\w\.\-_]+@[\w\.\-]+):(?:\/\/)?[\w\.@:\/~_-]+\.git(?:\/?|\#[\d\w\.\-_]+?)$/', $uri);

    return match ($type) {
      'any' => $is_local || $is_external,
      'local' => $is_local,
      'external' => $is_external,
      default => throw new \InvalidArgumentException(sprintf('Invalid argument "%s" provided', $type)),
    };
  }

}
