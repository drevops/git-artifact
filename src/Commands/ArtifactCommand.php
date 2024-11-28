<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Commands;

use DrevOps\GitArtifact\Git\ArtifactGit;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use DrevOps\GitArtifact\Traits\FilesystemTrait;
use DrevOps\GitArtifact\Traits\LogTrait;
use DrevOps\GitArtifact\Traits\TokenTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Artifact Command.
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class ArtifactCommand extends Command {

  use TokenTrait;
  use FilesystemTrait;
  use LogTrait;

  const GIT_REMOTE_NAME = 'dst';

  /**
   * Represent to current repository.
   */
  protected ArtifactGitRepository $gitRepository;

  /**
   * Source path of git repository.
   */
  protected string $sourcePathGitRepository = '';

  /**
   * Mode in which current build is going to run.
   *
   * Available modes: branch, force-push, diff.
   */
  protected string $mode;

  /**
   * Original branch in current repository.
   */
  protected string $originalBranch = '';

  /**
   * Destination branch with optional tokens.
   */
  protected string $destinationBranch = '';

  /**
   * Local branch where artifact will be built.
   */
  protected string $artifactBranch = '';

  /**
   * Remote name.
   */
  protected string $remoteName = '';

  /**
   * Remote URL includes uri or local path.
   */
  protected string $remoteUrl = '';

  /**
   * Gitignore file to be used during artifact creation.
   *
   * If not set, the current `.gitignore` will be used, if any.
   */
  protected ?string $gitignoreFile = NULL;

  /**
   * Commit message with optional tokens.
   */
  protected string $message = '';

  /**
   * Flag to specify if using dry run.
   */
  protected bool $isDryRun = FALSE;

  /**
   * Flag to specify if cleanup is required to run after the build.
   */
  protected bool $needCleanup = TRUE;

  /**
   * Path to log file.
   */
  protected string $logFile = '';

  /**
   * Flag to show changes made to the repo by the build in the output.
   */
  protected bool $showChanges = FALSE;

  /**
   * Artifact build result.
   */
  protected bool $result = FALSE;

  /**
   * Internal option to set current timestamp.
   */
  protected int $now;

  /**
   * Output.
   */
  protected OutputInterface $output;

  /**
   * Git wrapper.
   */
  protected ArtifactGit $git;

  /**
   * Artifact constructor.
   *
   * @param \DrevOps\GitArtifact\Git\ArtifactGit $gitWrapper
   *   Git wrapper.
   * @param \Symfony\Component\Filesystem\Filesystem $fsFileSystem
   *   File system.
   * @param string|null $name
   *   Command name.
   */
  public function __construct(
    ?ArtifactGit $gitWrapper = NULL,
    ?Filesystem $fsFileSystem = NULL,
    ?string $name = NULL,
  ) {
    parent::__construct($name);
    $this->fsFileSystem = is_null($fsFileSystem) ? new Filesystem() : $fsFileSystem;
    $this->git = is_null($gitWrapper) ? new ArtifactGit() : $gitWrapper;
  }

  /**
   * Configure command.
   */
  protected function configure(): void {
    $this->setName('artifact');

    $this->setDescription('Assemble a code artifact from your codebase, remove unnecessary files, and push it into a separate Git repository.');

    $this->addArgument('remote', InputArgument::REQUIRED, 'Path to the remote git repository.');

    $this
      ->addOption('branch', NULL, InputOption::VALUE_REQUIRED, 'Destination branch with optional tokens.', '[branch]')
      ->addOption(
          'gitignore',
          NULL,
          InputOption::VALUE_REQUIRED,
          'Path to gitignore file to replace current .gitignore.'
      )
      ->addOption(
          'message',
          NULL,
          InputOption::VALUE_REQUIRED,
          'Commit message with optional tokens.',
          'Deployment commit'
      )
      ->addOption(
          'mode',
          NULL,
          InputOption::VALUE_REQUIRED,
          'Mode of artifact build: branch, force-push or diff. Defaults to force-push.',
          'force-push'
      )
      ->addOption('no-cleanup', NULL, InputOption::VALUE_NONE, 'Do not cleanup after run.')
      ->addOption('now', NULL, InputOption::VALUE_REQUIRED, 'Internal value used to set internal time.')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Run without pushing to the remote repository.')
      ->addOption('log', NULL, InputOption::VALUE_REQUIRED, 'Path to the log file.')
      ->addOption(
          'root',
          NULL,
          InputOption::VALUE_REQUIRED,
          'Path to the root for file path resolution. If not specified, current directory is used.'
      )
      ->addOption(
          'show-changes',
          NULL,
          InputOption::VALUE_NONE,
          'Show changes made to the repo by the build in the output.'
      )
      ->addOption(
          'src',
          NULL,
          InputOption::VALUE_REQUIRED,
          'Directory where source repository is located. If not specified, root directory is used.'
      );
  }

  /**
   * Perform actual command.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   *
   * @return int
   *   Status code.
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // If log option was set, we set verbosity is debug.
    if ($input->getOption('log')) {
      $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
    }
    $this->output = $output;
    $tmpLogFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . time() . '-artifact-log.log';
    $this->logger = self::createLogger((string) $this->getName(), $output, $tmpLogFile);
    $remote = $input->getArgument('remote');
    try {
      // Now we have all what we need.
      // Let process artifact function.
      $this->checkRequirements();
      // @phpstan-ignore-next-line
      $this->processArtifact($remote, $input->getOptions());

      // Dump log file and clean tmp log file.
      if ($this->fsFileSystem->exists($tmpLogFile)) {
        if (!empty($this->logFile)) {
          $this->fsFileSystem->copy($tmpLogFile, $this->logFile);
        }
        $this->fsFileSystem->remove($tmpLogFile);
      }
    }
    catch (\Exception $exception) {
      $this->output->writeln([
        '<error>Deployment failed.</error>',
        '<error>' . $exception->getMessage() . '</error>',
      ]);
      return Command::FAILURE;
    }

    $this->output->writeln('<info>Deployment finished successfully.</info>');

    return Command::SUCCESS;
  }

  /**
   * Assemble a code artifact from your codebase.
   *
   * @param string $remote
   *   Path to the remote git repository.
   * @param array<mixed> $options
   *   Options.
   *
   * @throws \Exception
   */
  protected function processArtifact(string $remote, array $options): void {
    try {
      $error = NULL;
      $this->logDebug('Debug messages enabled');
      // Let resolve options into properties first.
      $this->resolveOptions($remote, $options);
      $this->setupRemoteForRepository();
      $this->showInfo();
      $this->prepareArtifact();

      if ($this->isDryRun) {
        $this->output->writeln('<comment>Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.</comment>');
      }
      else {
        $this->doPush();
      }
      $this->result = TRUE;
    }
    catch (\Exception $exception) {
      // Capture message and allow to rollback.
      $error = $exception->getMessage();
    }

    $this->logReport();

    if ($this->needCleanup) {
      $this->cleanup();
    }

    if (!$this->result) {
      throw new \Exception((string) $error);
    }
  }

  /**
   * Get source path git repository.
   *
   * @return string
   *   Source path.
   */
  public function getSourcePathGitRepository(): string {
    return $this->sourcePathGitRepository;
  }

  /**
   * Branch mode.
   *
   * @return string
   *   Branch mode name.
   */
  public static function modeBranch(): string {
    return 'branch';
  }

  /**
   * Force-push mode.
   *
   * @return string
   *   Force-push mode name.
   */
  public static function modeForcePush(): string {
    return 'force-push';
  }

  /**
   * Diff mode.
   *
   * @return string
   *   Diff mode name.
   */
  public static function modeDiff(): string {
    return 'diff';
  }

  /**
   * Prepare artifact to be then deployed.
   *
   * @throws \Exception
   */
  protected function prepareArtifact(): void {
    // Switch to artifact branch.
    $this->switchToArtifactBranchInGitRepository();
    // Remove sub-repositories.
    $this->removeSubReposInGitRepository();
    // Disable local exclude.
    $this->disableLocalExclude($this->getSourcePathGitRepository());
    // Add files.
    $this->addAllFilesInGitRepository();
    // Remove other files.
    $this->removeOtherFilesInGitRepository();
    // Commit all changes.
    $result = $this->commitAllChangesInGitRepository();
    // Show all changes if needed.
    if ($this->showChanges) {
      $this->output->writeln(sprintf('Added changes: %s', implode("\n", $result)));
      $this->logNotice(sprintf('Added changes: %s', implode("\n", $result)));
    }
  }

  /**
   * Switch to artifact branch.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  protected function switchToArtifactBranchInGitRepository(): void {
    $this
      ->gitRepository
      ->switchToBranch($this->artifactBranch, TRUE);
  }

  /**
   * Commit all changes.
   *
   * @return string[]
   *   The files committed.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  protected function commitAllChangesInGitRepository(): array {
    return $this
      ->gitRepository
      ->commitAllChanges($this->message);

  }

  /**
   * Add all files in current git repository.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \Exception
   */
  protected function addAllFilesInGitRepository(): void {
    if (!empty($this->gitignoreFile)) {
      $this->replaceGitignoreInGitRepository($this->gitignoreFile);
      $this->gitRepository->addAllChanges();
      $this->removeIgnoredFiles($this->getSourcePathGitRepository());
    }
    else {
      $this->gitRepository->addAllChanges();
    }
  }

  /**
   * Cleanup after build.
   *
   * @throws \Exception
   */
  protected function cleanup(): void {
    $this
      ->restoreLocalExclude($this->getSourcePathGitRepository());

    $this
      ->gitRepository
      ->switchToBranch($this->originalBranch);

    $this
      ->gitRepository
      ->removeBranch($this->artifactBranch, TRUE);

    $this
      ->gitRepository
      ->removeRemote($this->remoteName);
  }

  /**
   * Perform actual push to remote.
   *
   * @throws \Exception
   */
  protected function doPush(): void {
    try {
      $refSpec = sprintf('refs/heads/%s:refs/heads/%s', $this->artifactBranch, $this->destinationBranch);
      if ($this->mode === self::modeForcePush()) {
        $this
          ->gitRepository
          ->pushForce($this->remoteName, $refSpec);
      }
      else {
        $this->gitRepository->push([$this->remoteName, $refSpec]);
      }

      $this->output->writeln(sprintf('<info>Pushed branch "%s" with commit message "%s"</info>', $this->destinationBranch, $this->message));
    }
    catch (\Exception $exception) {
      // Re-throw the message with additional context.
      throw new \Exception(
        sprintf(
          'Error occurred while pushing branch "%s" with commit message "%s"',
          $this->destinationBranch,
          $this->message
        ),
        $exception->getCode(),
        $exception
      );
    }
  }

  /**
   * Resolve and validate CLI options values into internal values.
   *
   * @param string $remote
   *   Remote URL.
   * @param array $options
   *   Array of CLI options.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \Exception
   *
   * @phpstan-ignore-next-line
   */
  protected function resolveOptions(string $remote, array $options): void {
    // First handle root for filesystem.
    $this->fsSetRootDir($options['root']);

    // Resolve some basic options into properties.
    $this->showChanges = !empty($options['show-changes']);
    $this->needCleanup = empty($options['no-cleanup']);
    $this->isDryRun = !empty($options['dry-run']);
    $this->logFile = empty($options['log']) ? '' : $this->fsGetAbsolutePath($options['log']);
    $this->now = empty($options['now']) ? time() : (int) $options['now'];
    $this->remoteName = self::GIT_REMOTE_NAME;
    $this->remoteUrl = $remote;
    $this->setMode($options['mode'], $options);

    // Handle some complex options.
    $srcPath = empty($options['src']) ? $this->fsGetRootDir() : $this->fsGetAbsolutePath($options['src']);
    $this->sourcePathGitRepository = $srcPath;
    // Setup Git repository from source path.
    $this->initGitRepository($srcPath);
    // Set original, destination, artifact branch name.
    $this->originalBranch = $this->resolveOriginalBranch();
    $this->setDstBranch($options['branch']);
    $this->artifactBranch = $this->destinationBranch . '-artifact';
    // Set commit message.
    $this->setMessage($options['message']);
    // Set git ignore file path.
    if (!empty($options['gitignore'])) {
      $this->setGitignoreFile($options['gitignore']);
    }

  }

  /**
   * Setup git repository.
   *
   * @param string $sourcePath
   *   Source path.
   *
   * @return \DrevOps\GitArtifact\Git\ArtifactGitRepository
   *   Current git repository.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \Exception
   */
  protected function initGitRepository(string $sourcePath): ArtifactGitRepository {
    $this->gitRepository = $this->git->open($sourcePath);

    return $this->gitRepository;
  }

  /**
   * Show artifact build information.
   */
  protected function showInfo(): void {
    $lines[] = ('----------------------------------------------------------------------');
    $lines[] = (' Artifact information');
    $lines[] = ('----------------------------------------------------------------------');
    $lines[] = (' Build timestamp:       ' . date('Y/m/d H:i:s', $this->now));
    $lines[] = (' Mode:                  ' . $this->mode);
    $lines[] = (' Source repository:     ' . $this->getSourcePathGitRepository());
    $lines[] = (' Remote repository:     ' . $this->remoteUrl);
    $lines[] = (' Remote branch:         ' . $this->destinationBranch);
    $lines[] = (' Gitignore file:        ' . ($this->gitignoreFile ?: 'No'));
    $lines[] = (' Will push:             ' . ($this->isDryRun ? 'No' : 'Yes'));
    $lines[] = ('----------------------------------------------------------------------');

    $this->output->writeln($lines);
    foreach ($lines as $line) {
      $this->logNotice($line);
    }
  }

  /**
   * Dump artifact report to a file.
   */
  protected function logReport(): void {
    $lines[] = '----------------------------------------------------------------------';
    $lines[] = ' Artifact report';
    $lines[] = '----------------------------------------------------------------------';
    $lines[] = ' Build timestamp:   ' . date('Y/m/d H:i:s', $this->now);
    $lines[] = ' Mode:              ' . $this->mode;
    $lines[] = ' Source repository: ' . $this->getSourcePathGitRepository();
    $lines[] = ' Remote repository: ' . $this->remoteUrl;
    $lines[] = ' Remote branch:     ' . $this->destinationBranch;
    $lines[] = ' Gitignore file:    ' . ($this->gitignoreFile ?: 'No');
    $lines[] = ' Commit message:    ' . $this->message;
    $lines[] = ' Push result:       ' . ($this->result ? 'Success' : 'Failure');
    $lines[] = '----------------------------------------------------------------------';

    foreach ($lines as $line) {
      $this->logNotice($line);
    }
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
  protected function setMode(string $mode, array $options): void {
    switch ($mode) {
      case self::modeForcePush():
        // Intentionally empty.
        break;

      case self::modeBranch():
        if (!$this->hasToken($options['branch'])) {
          $this->output->writeln('<comment>WARNING! Provided branch name does not have a token.
                    Pushing of the artifact into this branch will fail on second and follow up pushes to remote.
                    Consider adding tokens with unique values to the branch name.</comment>');
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
   * Resolve original branch to handle detached repositories.
   *
   * Usually, repository become detached when a tag is checked out.
   *
   * @return string
   *   Branch or detachment source.
   *
   * @throws \Exception
   *   If neither branch nor detachment source is not found.
   */
  protected function resolveOriginalBranch(): string {
    $branch = $this->gitRepository->getCurrentBranchName();
    // Repository could be in detached state. If this the case - we need to
    // capture the source of detachment, if it exists.
    if (str_contains($branch, 'HEAD detached')) {
      $branch = NULL;
      $branchList = $this->gitRepository->getBranches();
      if ($branchList) {
        $branchList = array_filter($branchList);
        foreach ($branchList as $branch) {
          if (preg_match('/\(.*detached .* ([^\)]+)\)/', $branch, $matches)) {
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
  protected function setDstBranch(string $branch): void {
    $branch = (string) $this->tokenProcess($branch);

    if (!ArtifactGitRepository::isValidBranchName($branch)) {
      throw new \RuntimeException(sprintf('Incorrect value "%s" specified for git remote branch', $branch));
    }
    $this->destinationBranch = $branch;
  }

  /**
   * Set commit message.
   *
   * @param string $message
   *   Commit message to set on the deployment commit.
   */
  protected function setMessage(string $message): void {
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
  protected function setGitignoreFile(string $path): void {
    $path = $this->fsGetAbsolutePath($path);
    $this->fsPathsExist($path);
    $this->gitignoreFile = $path;
  }

  /**
   * Check that there all requirements are met in order to to run this command.
   */
  protected function checkRequirements(): void {
    // @todo Refactor this into more generic implementation.
    $this->logNotice('Checking requirements');
    if (!$this->fsIsCommandAvailable('git')) {
      throw new \RuntimeException('At least one of the script running requirements was not met');
    }
    $this->logNotice('All requirements were met');
  }

  /**
   * Replace gitignore file with provided file.
   *
   * @param string $filename
   *   Path to new gitignore to replace current file with.
   */
  protected function replaceGitignoreInGitRepository(string $filename): void {
    $path = $this->getSourcePathGitRepository();
    $this->logDebug(sprintf('Replacing .gitignore: %s with %s', $path . DIRECTORY_SEPARATOR . '.gitignore', $filename));
    $this->fsFileSystem->copy($filename, $path . DIRECTORY_SEPARATOR . '.gitignore', TRUE);
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
  protected function getLocalExcludeFileName(string $path): string {
    return $path . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'info' . DIRECTORY_SEPARATOR . 'exclude';
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
  protected function localExcludeExists(string $path): bool {
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
  protected function localExcludeEmpty(string $path, bool $strict = FALSE): bool {
    if (!$this->localExcludeExists($path)) {
      throw new \Exception(sprintf('File "%s" does not exist', $path));
    }

    $filename = $this->getLocalExcludeFileName($path);
    if ($strict) {
      return empty(file_get_contents($filename));
    }
    $lines = file($filename);
    if ($lines) {
      $lines = array_map(trim(...), $lines);
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
  protected function disableLocalExclude(string $path): void {
    $filename = $this->getLocalExcludeFileName($path);
    $filenameDisabled = $filename . '.bak';
    if ($this->fsFileSystem->exists($filename)) {
      $this->logDebug('Disabling local exclude');
      $this->fsFileSystem->rename($filename, $filenameDisabled);
    }
  }

  /**
   * Restore previously disabled local exclude file.
   *
   * @param string $path
   *   Path to repository.
   */
  protected function restoreLocalExclude(string $path): void {
    $filename = $this->getLocalExcludeFileName($path);
    $filenameDisabled = $filename . '.bak';
    if ($this->fsFileSystem->exists($filenameDisabled)) {
      $this->logDebug('Restoring local exclude');
      $this->fsFileSystem->rename($filenameDisabled, $filename);
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
  protected function removeIgnoredFiles(string $location, ?string $gitignorePath = NULL): void {
    $location = $this->getSourcePathGitRepository();
    $gitignorePath = $gitignorePath ?: $location . DIRECTORY_SEPARATOR . '.gitignore';

    $gitignoreContent = file_get_contents($gitignorePath);
    if (!$gitignoreContent) {
      $this->logDebug('Unable to load ' . $gitignoreContent);
    }
    else {
      $this->logDebug('-----.gitignore---------');
      $this->logDebug($gitignoreContent);
      $this->logDebug('-----.gitignore---------');
    }

    $files = $this
      ->gitRepository
      ->listIgnoredFilesFromGitIgnoreFile($gitignorePath);

    if (!empty($files)) {
      $files = array_filter($files);
      foreach ($files as $file) {
        $fileName = $location . DIRECTORY_SEPARATOR . $file;
        $this->logDebug(sprintf('Removing excluded file %s', $fileName));
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
   * @throws \Exception
   *   If removal command finished with an error.
   */
  protected function removeOtherFilesInGitRepository(): void {
    $files = $this->gitRepository->listOtherFiles();
    if (!empty($files)) {
      $files = array_filter($files);
      foreach ($files as $file) {
        $fileName = $this->getSourcePathGitRepository() . DIRECTORY_SEPARATOR . $file;
        $this->logDebug(sprintf('Removing other file %s', $fileName));
        $this->fsFileSystem->remove($fileName);
      }
    }
  }

  /**
   * Remove any repositories within current repository.
   */
  protected function removeSubReposInGitRepository(): void {
    $finder = new Finder();
    $dirs = $finder
      ->directories()
      ->name('.git')
      ->ignoreDotFiles(FALSE)
      ->ignoreVCS(FALSE)
      ->depth('>0')
      ->in($this->getSourcePathGitRepository());

    $dirs = iterator_to_array($dirs->directories());

    foreach ($dirs as $dir) {
      if ($dir instanceof \SplFileInfo) {
        $dir = $dir->getPathname();
      }
      $this->fsFileSystem->remove($dir);
      $this->logDebug(sprintf('Removing sub-repository "%s"', (string) $dir));
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
  protected function getTokenBranch(): string {
    return $this
      ->gitRepository
      ->getCurrentBranchName();
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
  protected function getTokenTags(?string $delimiter = NULL): string {
    $delimiter = $delimiter ?: '-';
    // We just want to get all tags point to the HEAD.
    $tags = $this
      ->gitRepository
      ->getTagsPointToHead();

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
  protected function getTokenTimestamp(string $format = 'Y-m-d_H-i-s'): string {
    return date($format, $this->now);
  }

  /**
   * Write output.
   *
   * @param string $text
   *   Text.
   */
  protected function writeln(string $text): void {
    $this->output->writeln($text);
  }

  /**
   * Setup remote for current repository.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \Exception
   */
  protected function setupRemoteForRepository(): void {
    $remoteName = $this->remoteName;
    $remoteUrl = $this->remoteUrl;
    if (!ArtifactGitRepository::isValidRemoteUrl($remoteUrl)) {
      throw new \Exception(sprintf('Invalid remote URL: %s', $remoteUrl));
    }

    $this->gitRepository->addRemote($remoteName, $remoteUrl);
  }

}
