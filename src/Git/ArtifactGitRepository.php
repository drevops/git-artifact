<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Git;

use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\IRunner;
use CzProject\GitPhp\RunnerResult;
use DrevOps\GitArtifact\Exception\BranchNotFoundException;
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
   * The standard gitignore file path.
   */
  protected ?string $gitignore = NULL;

  /**
   * The custom gitignore file path.
   */
  protected ?string $gitignoreCustom = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct($repository, ?IRunner $runner = NULL, ?LoggerInterface $logger = NULL) {
    parent::__construct($repository, $runner);

    $this->fs = new Filesystem();

    if ($logger instanceof LoggerInterface) {
      $this->logger = $logger;
    }

    $this->gitignore = $this->getRepositoryPath() . DIRECTORY_SEPARATOR . '.gitignore';
  }

  /**
   * {@inheritdoc}
   */
  public function run(...$args): RunnerResult {
    $command = reset($args);

    $no_pager = [
      'add',
      'commit',
      'checkout',
      'clone',
      'init',
      'status',
      'config',
      'push',
      'pull',
      'fetch',
      'merge',
      'rebase',
      'reset',
    ];
    if (!in_array($command, $no_pager)) {
      array_unshift($args, '--no-pager');
    }

    return parent::run(...$args);
  }

  /**
   * Set gitignore file.
   */
  public function setGitignoreCustom(string $filename): static {
    $this->gitignoreCustom = $filename;

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
   * Get remote branches with their tip commit timestamps.
   *
   * A shallow fetch populates the remote-tracking refs; only the tip commit
   * metadata is required to determine branch age, so the fetch is limited to a
   * depth of one.
   *
   * @param string $remote
   *   Remote name.
   *
   * @return array<string, int>
   *   Branch names keyed to the Unix timestamp of their tip commit.
   */
  public function getRemoteBranchesInfo(string $remote): array {
    $this->run('fetch', '--depth=1', $remote);

    $format = '%(refname:lstrip=3)|%(committerdate:unix)';
    $lines = $this->extractFromCommand(['for-each-ref', '--format=' . $format, 'refs/remotes/' . $remote]) ?: [];

    $branches = [];
    foreach ($lines as $line) {
      if (!str_contains($line, '|')) {
        continue;
      }

      [$name, $timestamp] = explode('|', $line, 2);

      if ($name === '' || $name === 'HEAD' || !is_numeric($timestamp)) {
        continue;
      }

      $branches[$name] = (int) $timestamp;
    }

    return $branches;
  }

  /**
   * Get the default branch of a remote.
   *
   * @param string $remote
   *   Remote name.
   *
   * @return string|null
   *   The default branch name, or NULL if it cannot be determined.
   */
  public function getRemoteDefaultBranch(string $remote): ?string {
    try {
      $lines = $this->extractFromCommand(['ls-remote', '--symref', $remote, 'HEAD']) ?: [];
    }
    catch (GitException) {
      return NULL;
    }

    foreach ($lines as $line) {
      if (preg_match('#^ref:\s+refs/heads/(\S+)\s+HEAD#', $line, $matches)) {
        return $matches[1];
      }
    }

    return NULL;
  }

  /**
   * Delete a branch in the remote repository.
   *
   * @param string $remote
   *   Remote name.
   * @param string $branch
   *   Branch name to delete.
   *
   * @return static
   *   The git repository.
   */
  public function deleteRemoteBranch(string $remote, string $branch): static {
    $this->run('push', $remote, '--delete', $branch);

    return $this;
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
        // Get current commit hash.
        $commit_hash = $this->execute(['rev-parse', 'HEAD'])[0] ?? '';

        throw new BranchNotFoundException(
          'Unable to determine a detachment source',
          $commit_hash
        );
      }

      // Validate that the extracted value is actually a branch or tag, not just
      // a commit hash. If it's only a commit hash, we cannot determine the
      // original branch.
      try {
        $this->execute(['show-ref', '--verify', 'refs/heads/' . $branch]);
      }
      catch (GitException $e1) {
        try {
          $this->execute(['show-ref', '--verify', 'refs/tags/' . $branch]);
        }
        catch (GitException $e2) {
          // Not a branch or tag - just a commit hash.
          // Get current commit hash.
          $commit_hash = $this->execute(['rev-parse', 'HEAD'])[0] ?? '';

          throw new BranchNotFoundException('Unable to determine a detachment source', $commit_hash, $e1);
        }
      }
    }

    return $branch;
  }

  /**
   * Remove ignored files.
   */
  public function removeIgnoredFiles(): static {
    $files = [];

    if ($this->gitignore !== NULL && file_exists($this->gitignore)) {
      $files = $this->extractFromCommand(['ls-files', '-i', '-c', '--exclude-from=' . $this->gitignore]) ?: [];
      $files = array_merge($files, array_filter($files));
    }

    if ($this->gitignore !== NULL && file_exists($this->gitignore)) {
      $files = $this->extractFromCommand(['ls-files', '-i', '-c', '--exclude-from=' . $this->gitignore]) ?: [];
      $files = array_merge($files, array_filter($files));
    }

    // Symlinks are not returned by the command above. We need to find them
    // manually and check if they are ignored.
    $symlinks_iterator = (new Finder())
      ->ignoreDotFiles(FALSE)
      ->ignoreVCS(TRUE)
      ->in($this->getRepositoryPath())
      ->filter(fn($file) => $file->isLink())
      ->getIterator();

    foreach ($symlinks_iterator as $file) {
      $path = $file->getRelativePathname();
      if ($this->isFileIgnored($path)) {
        $files[] = $path;
      }
    }

    foreach ($files as $file) {
      $filename = $this->getRepositoryPath() . DIRECTORY_SEPARATOR . $file;
      if ($this->fs->exists($filename) || is_link($filename)) {
        $this->logger->debug(sprintf('Removing ignored file %s', $filename));
        $this->fs->remove($filename);
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

  /**
   * Filter branches down to those eligible for stale cleanup.
   *
   * @param array<string, int> $branches
   *   Branch names keyed to the Unix timestamp of their tip commit.
   * @param array<string> $patterns
   *   Cleanup patterns (globs or regex literals); a branch matching any of them
   *   is eligible.
   * @param int $max_age
   *   Maximum allowed age in seconds; branches older than this are stale.
   * @param int $now
   *   Reference "current" Unix timestamp to measure age against.
   * @param array<string> $protected_branches
   *   Branch names that must never be returned.
   *
   * @return array<string>
   *   Names of stale branches, sorted for deterministic output.
   */
  public static function filterStaleBranches(array $branches, array $patterns, int $max_age, int $now, array $protected_branches = []): array {
    $stale = [];

    foreach ($branches as $name => $timestamp) {
      $name = (string) $name;

      if (in_array($name, $protected_branches, TRUE)) {
        continue;
      }

      if (!self::matchesAnyPattern($patterns, $name)) {
        continue;
      }

      if (($now - $timestamp) <= $max_age) {
        continue;
      }

      $stale[] = $name;
    }

    sort($stale);

    return $stale;
  }

  /**
   * Determine whether a cleanup token is a regex rather than a glob.
   *
   * A Git branch name can never begin with a slash, so a leading slash
   * unambiguously marks a PCRE literal (delimiters and flags included), e.g.
   * '/^feature\/[^\/]+$/'. Everything else is treated as a shell-style glob.
   *
   * @param string $pattern
   *   The cleanup token to inspect.
   *
   * @return bool
   *   TRUE when the token should be interpreted as a regular expression.
   */
  public static function isRegexPattern(string $pattern): bool {
    return str_starts_with($pattern, '/');
  }

  /**
   * Test whether a string is a usable PCRE pattern.
   *
   * @param string $pattern
   *   A regex literal including its delimiters and any flags.
   *
   * @return bool
   *   TRUE when the pattern compiles.
   */
  public static function isValidRegex(string $pattern): bool {
    set_error_handler(static fn(): bool => TRUE);

    try {
      return preg_match($pattern, '') !== FALSE;
    }
    finally {
      restore_error_handler();
    }
  }

  /**
   * Test whether a branch name matches any of the given patterns.
   *
   * @param array<string> $patterns
   *   Cleanup patterns, each a shell glob or a regex literal (see
   *   ::isRegexPattern()).
   * @param string $subject
   *   The branch name to test.
   *
   * @return bool
   *   TRUE when the subject matches at least one pattern.
   */
  protected static function matchesAnyPattern(array $patterns, string $subject): bool {
    foreach ($patterns as $pattern) {
      if (self::matchesPattern($pattern, $subject)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Test whether a branch name matches a single cleanup pattern.
   *
   * @param string $pattern
   *   A shell glob or a regex literal (see ::isRegexPattern()).
   * @param string $subject
   *   The branch name to test.
   *
   * @return bool
   *   TRUE when the subject matches the pattern.
   */
  protected static function matchesPattern(string $pattern, string $subject): bool {
    if (self::isRegexPattern($pattern)) {
      return preg_match($pattern, $subject) === 1;
    }

    return self::matchesGlob($pattern, $subject);
  }

  /**
   * Test whether a branch name matches a shell-style glob pattern.
   *
   * Converts the glob into a regular expression so the match honours the
   * same '*' and '?' semantics as a shell glob without using fnmatch().
   *
   * @param string $pattern
   *   The glob pattern to match against (e.g. 'deployment/*').
   * @param string $subject
   *   The string to test.
   *
   * @return bool
   *   TRUE when the subject matches the pattern.
   */
  protected static function matchesGlob(string $pattern, string $subject): bool {
    $regex = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';

    return (bool) preg_match($regex, $subject);
  }

  /**
   * Reset to the previous commit.
   */
  public function resetToPreviousCommit(): static {
    $this->run('reset', '--soft', 'HEAD~1');

    return $this;
  }

  /**
   * Replace .gitignore with content from custom .gitignore file.
   */
  public function replaceGitignoreFromCustom(): static {
    if ($this->gitignoreCustom !== NULL && !empty($this->gitignoreCustom) && $this->gitignore !== NULL) {
      $this->logger->debug(sprintf('Copying custom .gitignore file from %s to %s', $this->gitignoreCustom, $this->gitignore));
      $this->fs->rename($this->gitignoreCustom, $this->gitignore, TRUE);
    }

    return $this;
  }

  /**
   * Restore .gitignore content to custom .gitignore file if it existed.
   */
  public function restoreGitignoreToCustom(): static {
    if ($this->gitignoreCustom !== NULL && $this->gitignore !== NULL && file_exists($this->gitignore)) {
      $this->logger->debug(sprintf('Restoring custom .gitignore file from %s to %s', $this->gitignore, $this->gitignoreCustom));
      $this->fs->rename($this->gitignore, $this->gitignoreCustom, TRUE);
    }

    return $this;
  }

  /**
   * Unstage all changes.
   */
  public function unstageAllChanges(): static {
    $this->run('reset', 'HEAD');

    return $this;
  }

  /**
   * Check if file is ignored.
   *
   * @param string $file
   *   File to check.
   *
   * @return bool
   *   TRUE if file is ignored, FALSE otherwise.
   */
  public function isFileIgnored(string $file): bool {
    try {
      $this->extractFromCommand(['check-ignore', '-v', $file]);
    }
    catch (GitException $exception) {
      if ($exception->getCode() === 1) {
        return FALSE;
      }

      throw $exception;
    }

    return TRUE;
  }

}
