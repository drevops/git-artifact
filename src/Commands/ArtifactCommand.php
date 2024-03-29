<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Commands;

use DrevOps\GitArtifact\FilesystemTrait;
use DrevOps\GitArtifact\GitArtifactGit;
use DrevOps\GitArtifact\GitArtifactGitRepository;
use DrevOps\GitArtifact\TokenTrait;
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

  const GIT_REMOTE_NAME = 'dst';

  /**
   * Represent to current repository.
   */
  protected GitArtifactGitRepository $gitRepository;

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
   * Flag to specify if push is required or should be using dry run.
   */
  protected bool $needsPush = FALSE;

  /**
   * Flag to specify if cleanup is required to run after the build.
   */
  protected bool $needCleanup = TRUE;

  /**
   * Path to report file.
   */
  protected string $reportFile = '';

  /**
   * Flag to show changes made to the repo by the build in the output.
   */
  protected bool $showChanges = FALSE;

  /**
   * Artifact build result.
   */
  protected bool $result = FALSE;

  /**
   * Flag to print debug information.
   */
  protected bool $debug = FALSE;

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
  protected GitArtifactGit $git;

  /**
   * Artifact constructor.
   *
   * @param \DrevOps\GitArtifact\GitArtifactGit $gitWrapper
   *   Git wrapper.
   * @param \Symfony\Component\Filesystem\Filesystem $fsFileSystem
   *   File system.
   * @param string|null $name
   *   Command name.
   */
  public function __construct(
    GitArtifactGit $gitWrapper = NULL,
    Filesystem $fsFileSystem = NULL,
    ?string $name = NULL
  ) {
    parent::__construct($name);
    $this->fsFileSystem = is_null($fsFileSystem) ? new Filesystem() : $fsFileSystem;
    $this->git = is_null($gitWrapper) ? new GitArtifactGit() : $gitWrapper;
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
      ->addOption('debug', NULL, InputOption::VALUE_NONE, 'Print debug information.')
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
      ->addOption('push', NULL, InputOption::VALUE_NONE, 'Push artifact to the remote repository')
      ->addOption('report', NULL, InputOption::VALUE_REQUIRED, 'Path to the report file.')
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
    $this->output = $output;
    try {
      $this->checkRequirements();
      $remote = $input->getArgument('remote');
      // @phpstan-ignore-next-line
      $this->processArtifact($remote, $input->getOptions());
    }
    catch (\Exception $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');

      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Assemble a code artifact from your codebase.
   *
   * @param string $remote
   *   Path to the remote git repository.
   * @param array $opts
   *   Options.
   *
   * @option $branch Destination branch with optional tokens.
   * @option $debug Print debug information.
   * @option $gitignore Path to gitignore file to replace current .gitignore.
   * @option $message Commit message with optional tokens.
   * @option $mode Mode of artifact build: branch, force-push or diff.
   *   Defaults to force-push.
   * @option $now Internal value used to set internal time.
   * @option $no-cleanup Do not cleanup after run.
   * @option $push Push artifact to the remote repository. Defaults to FALSE.
   * @option $report Path to the report file.
   * @option $root Path to the root for file path resolution. If not
   *         specified, current directory is used.
   * @option $show-changes Show changes made to the repo by the build in the
   *         output.
   * @option $src Directory where source repository is located. If not
   *   specified, root directory is used.
   *
   * @throws \Exception
   *
   * @phpstan-ignore-next-line
   */
  protected function processArtifact(string $remote, array $opts = [
    'branch' => '[branch]',
    'debug' => FALSE,
    'gitignore' => '',
    'message' => 'Deployment commit',
    'mode' => 'force-push',
    'no-cleanup' => FALSE,
    'now' => '',
    'push' => FALSE,
    'report' => '',
    'root' => '',
    'show-changes' => FALSE,
    'src' => '',
  ]): void {
    try {
      $error = NULL;
      $this->resolveOptions($remote, $opts);

      // Now we have all what we need.
      // Let process artifact function.
      $this->printDebug('Debug messages enabled');

      $this->setupRemoteForRepository();
      $this->showInfo();
      $this->prepareArtifact();

      if ($this->needsPush) {
        $this->doPush();
      }
      else {
        $this->yell('Cowardly refusing to push to remote. Use --push option to perform an actual push.');
      }
      $this->result = TRUE;
    }
    catch (\Exception $exception) {
      // Capture message and allow to rollback.
      $error = $exception->getMessage();
    }

    if (!empty($this->reportFile)) {
      $this->dumpReport();
    }

    if ($this->needCleanup) {
      $this->cleanup();
    }

    if ($this->result) {
      $this->say('Deployment finished successfully.');
    }
    else {
      $this->say('Deployment failed.');
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
      $this->say(sprintf('Added changes: %s', implode("\n", $result)));
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

      $this->sayOkay(sprintf('Pushed branch "%s" with commit message "%s"', $this->destinationBranch, $this->message));
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
    $this->needsPush = !empty($options['push']);
    $this->reportFile = empty($options['report']) ? '' : $options['report'];
    $this->now = empty($options['now']) ? time() : (int) $options['now'];
    $this->debug = !empty($options['debug']);
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
   * @return \DrevOps\GitArtifact\GitArtifactGitRepository
   *   Current git repository.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \Exception
   */
  protected function initGitRepository(string $sourcePath): GitArtifactGitRepository {
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
    $lines[] = (' Will push:             ' . ($this->needsPush ? 'Yes' : 'No'));
    $lines[] = ('----------------------------------------------------------------------');
    $this->output->writeln($lines);
  }

  /**
   * Dump artifact report to a file.
   */
  protected function dumpReport(): void {
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

    $this->fsFileSystem->dumpFile($this->reportFile, implode(PHP_EOL, $lines));
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
    $this->say(sprintf('Running in "%s" mode', $mode));

    switch ($mode) {
      case self::modeForcePush():
        // Intentionally empty.
        break;

      case self::modeBranch():
        if (!$this->hasToken($options['branch'])) {
          $this->say('WARNING! Provided branch name does not have a token.
                    Pushing of the artifact into this branch will fail on second and follow up pushes to remote.
                    Consider adding tokens with unique values to the branch name.');
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

    if (!GitArtifactGitRepository::isValidBranchName($branch)) {
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
  protected function replaceGitignoreInGitRepository(string $filename): void {
    $path = $this->getSourcePathGitRepository();
    $this->printDebug('Replacing .gitignore: %s with %s', $path . DIRECTORY_SEPARATOR . '.gitignore', $filename);
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
      $lines = array_map('trim', $lines);
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
      $this->printDebug('Disabling local exclude');
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
      $this->printDebug('Restoring local exclude');
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
  protected function removeIgnoredFiles(string $location, string $gitignorePath = NULL): void {
    $location = $this->getSourcePathGitRepository();
    $gitignorePath = $gitignorePath ?: $location . DIRECTORY_SEPARATOR . '.gitignore';

    $gitignoreContent = file_get_contents($gitignorePath);
    if (!$gitignoreContent) {
      $this->printDebug('Unable to load ' . $gitignoreContent);
    }
    else {
      $this->printDebug('-----.gitignore---------');
      $this->printDebug($gitignoreContent);
      $this->printDebug('-----.gitignore---------');
    }

    $files = $this
      ->gitRepository
      ->listIgnoredFilesFromGitIgnoreFile($gitignorePath);

    if (!empty($files)) {
      $files = array_filter($files);
      foreach ($files as $file) {
        $fileName = $location . DIRECTORY_SEPARATOR . $file;
        $this->printDebug('Removing excluded file %s', $fileName);
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
        $this->printDebug('Removing other file %s', $fileName);
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
      $this->printDebug('Removing sub-repository "%s"', (string) $dir);
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
  protected function getTokenTags(string $delimiter = NULL): string {
    $delimiter = $delimiter ?: '-';
    $tags = $this
      ->gitRepository
      ->getTags();

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
   * Check if running in debug mode.
   *
   * @return bool
   *   Check is debugging mode or not.
   */
  protected function isDebug(): bool {
    return $this->debug || $this->output->isDebug();
  }

  /**
   * Write line as yell style.
   *
   * @param string $text
   *   Text yell.
   */
  protected function yell(string $text): void {
    $color = 'green';
    $char = $this->decorationCharacter('>', '➜');
    $format = sprintf('<fg=white;bg=%s;options=bold>%%s %%s</fg=white;bg=%s;options=bold>', $color, $color);
    $this->writeln(sprintf($format, $char, $text));
  }

  /**
   * Write line as say style.
   *
   * @param string $text
   *   Text.
   */
  protected function say(string $text): void {
    $char = $this->decorationCharacter('>', '➜');
    $this->writeln(sprintf('%s  %s', $char, $text));
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
  protected function sayOkay(string $text): void {
    $color = 'green';
    $char = $this->decorationCharacter('V', '✔');
    $format = sprintf('<fg=white;bg=%s;options=bold>%%s %%s</fg=white;bg=%s;options=bold>', $color, $color);
    $this->writeln(sprintf($format, $char, $text));
  }

  /**
   * Print debug information.
   *
   * @param mixed ...$args
   *   The args.
   */
  protected function printDebug(mixed ...$args): void {
    if (!$this->isDebug()) {
      return;
    }
    $message = array_shift($args);
    /* @phpstan-ignore-next-line */
    $this->writeln(vsprintf($message, $args));
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
   * Decoration character.
   *
   * @param string $nonDecorated
   *   Non decorated.
   * @param string $decorated
   *   Decorated.
   *
   * @return string
   *   The decoration character.
   */
  protected function decorationCharacter(string $nonDecorated, string $decorated): string {
    if (!$this->output->isDecorated() || (strncasecmp(PHP_OS, 'WIN', 3) === 0)) {
      return $nonDecorated;
    }

    return $decorated;
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
    if (!GitArtifactGitRepository::isValidRemoteUrl($remoteUrl)) {
      throw new \Exception(sprintf('Invalid remote URL: %s', $remoteUrl));
    }

    $this->gitRepository->addRemote($remoteName, $remoteUrl);
  }

}
