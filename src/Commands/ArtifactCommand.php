<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Commands;

use CzProject\GitPhp\GitException;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use DrevOps\GitArtifact\Traits\FilesystemTrait;
use DrevOps\GitArtifact\Traits\LoggerTrait;
use DrevOps\GitArtifact\Traits\TokenTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Artifact Command.
 */
class ArtifactCommand extends Command {

  use TokenTrait;
  use FilesystemTrait;
  use LoggerTrait;

  const GIT_REMOTE_NAME = 'dst';

  const MODE_BRANCH = 'branch';

  const MODE_FORCE_PUSH = 'force-push';

  /**
   * Current Git repository.
   */
  protected ArtifactGitRepository $repo;

  /**
   * Path to the dir of the source git repository.
   */
  protected string $sourceDir = '';

  /**
   * Mode in which current build is going to run.
   *
   * Available modes: branch, force-push.
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
   * Remote URL includes URI or local path.
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
  protected string $commitMessage = '';

  /**
   * Flag to specify if using dry run.
   */
  protected bool $isDryRun = FALSE;

  /**
   * Flag to specify if cleanup is required to run after the build.
   */
  protected bool $needCleanup = TRUE;

  /**
   * Path to the log file.
   */
  protected string $logFile = '';

  /**
   * Flag to show changes made to the repo by the build in the output.
   */
  protected bool $showChanges = FALSE;

  /**
   * Flag to specify if push was successful.
   */
  protected bool $pushSuccessful = FALSE;

  /**
   * Internal option to set current timestamp.
   */
  protected int $now;

  /**
   * Output interface.
   */
  protected OutputInterface $output;

  /**
   * Artifact constructor.
   *
   * @param string|null $name
   *   File system.
   * @param \Symfony\Component\Filesystem\Filesystem $fs
   *   Command name.
   */
  public function __construct(
    ?string $name = NULL,
    ?Filesystem $fs = NULL,
  ) {
    parent::__construct($name);
    $this->fs = is_null($fs) ? new Filesystem() : $fs;
  }

  /**
   * Configure command.
   */
  protected function configure(): void {
    $this->setName('artifact');

    $this->setDescription('Assemble a code artifact from your codebase, remove unnecessary files, and push it into a separate Git repository.');

    $this->addArgument('remote', InputArgument::REQUIRED, 'Path to the remote git repository.');

    // @formatter:off
    // phpcs:disable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
    // phpcs:disable Drupal.WhiteSpace.Comma.TooManySpaces
    $this
      ->addOption('branch',       NULL, InputOption::VALUE_REQUIRED, 'Destination branch with optional tokens.',                                                    '[branch]')
      ->addOption('dry-run',      NULL, InputOption::VALUE_NONE,     'Run without pushing to the remote repository.')
      ->addOption('gitignore',    NULL, InputOption::VALUE_REQUIRED, 'Path to gitignore file to replace current .gitignore. Leave empty to use current .gitignore.')
      ->addOption('message',      NULL, InputOption::VALUE_REQUIRED, 'Commit message with optional tokens.',                                                        'Deployment commit')
      ->addOption('mode',         NULL, InputOption::VALUE_REQUIRED, 'Mode of artifact build: branch, force-push. Defaults to force-push.',                         static::MODE_FORCE_PUSH)
      ->addOption('no-cleanup',   NULL, InputOption::VALUE_NONE,     'Do not cleanup after run.')
      ->addOption('now',          NULL, InputOption::VALUE_REQUIRED, 'Internal value used to set internal time.')
      ->addOption('log',          NULL, InputOption::VALUE_REQUIRED, 'Path to the log file.')
      ->addOption('root',         NULL, InputOption::VALUE_REQUIRED, 'Path to the root for file path resolution. If not specified, current directory is used.')
      ->addOption('show-changes', NULL, InputOption::VALUE_NONE,     'Show changes made to the repo by the build in the output.')
      ->addOption('src',          NULL, InputOption::VALUE_REQUIRED, 'Directory where source repository is located. If not specified, root directory is used.');
    // @formatter:on
    // phpcs:enable Generic.Functions.FunctionCallArgumentSpacing.TooMuchSpaceAfterComma
    // phpcs:enable Drupal.WhiteSpace.Comma.TooManySpaces
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

    $this->loggerInit((string) $this->getName(), $input, $output);

    $remote = $input->getArgument('remote');
    if (!is_string($remote) || empty(trim($remote))) {
      throw new \RuntimeException('Remote argument must be a non-empty string');
    }

    try {
      $this->checkRequirements();

      $this->resolveOptions($remote, $input->getOptions());

      $this->doExecute();
    }
    catch (\Exception $exception) {
      $this->output->writeln([
        '<error>Processing failed with an error:</error>',
        '<error>' . $exception->getMessage() . '</error>',
      ]);

      return Command::FAILURE;
    }

    $this->output->writeln('<info>Deployment finished successfully.</info>');

    return Command::SUCCESS;
  }

  /**
   * Assemble a code artifact from your codebase.
   */
  protected function doExecute(): void {
    $error = NULL;

    try {
      $this->repo->addRemote($this->remoteName, $this->remoteUrl);

      $this->showInfo();

      // Do not optimize this into a chained call to make it easier to debug.
      $repo = $this->repo;
      $repo->switchToBranch($this->artifactBranch, TRUE);
      $repo->removeSubRepositories();
      $repo->disableLocalExclude();
      $repo->replaceGitignoreFromCustom();
      // Custom .gitignore may contain rules that will change the list of
      // ignored files. We need to add these files as changes so that they
      // could be reported as excluded by the command below.
      $repo->addAllChanges();
      $repo->removeIgnoredFiles();
      $repo->removeOtherFiles();
      $changes = $repo->commitAllChanges($this->commitMessage);

      if ($this->showChanges) {
        $this->output->writeln(sprintf('Added changes: %s', implode("\n", $changes)));
        $this->logger->notice(sprintf('Added changes: %s', implode("\n", $changes)));
      }

      if ($this->isDryRun) {
        $this->output->writeln('<info>Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.</info>');
      }
      else {
        $ref = sprintf('refs/heads/%s:refs/heads/%s', $this->artifactBranch, $this->destinationBranch);

        if ($this->mode === self::MODE_FORCE_PUSH) {
          $this->repo->push([$this->remoteName, $ref], ['--force']);
        }
        else {
          $this->repo->push([$this->remoteName, $ref]);
        }

        $this->output->writeln(sprintf('<info>Pushed branch "%s" with commit message "%s"</info>', $this->destinationBranch, $this->commitMessage));
      }
    }
    catch (GitException $exception) {
      $result = $exception->getRunnerResult();
      if (!$result) {
        // @codeCoverageIgnoreStart
        throw new \Exception('Unknown error occurred', $exception->getCode(), $exception);
        // @codeCoverageIgnoreEnd
      }

      $error = $result->getOutputAsString();
      if (!empty($result->hasErrorOutput())) {
        $error .= PHP_EOL . $result->getErrorOutputAsString();
      }
    }
    catch (\Exception $exception) {
      // Capture message to allow showing a report.
      $error = $exception->getMessage();
    }

    $this->showReport(is_null($error));

    if ($this->needCleanup && is_null($error)) {
      $this->logger->notice('Cleaning up');
      $this->repo->resetToPreviousCommit();
      $this->repo->restoreGitignoreToCustom();
      $this->repo->restoreLocalExclude();
      $this->repo->unstageAllChanges();
      $this->repo->switchToBranch($this->originalBranch);
      $this->repo->removeBranch($this->artifactBranch, TRUE);
      $this->repo->removeRemote($this->remoteName);
    }

    // Dump log to a file.
    if (!empty($this->logFile)) {
      $this->loggerDump($this->logFile);
    }

    if (!is_null($error)) {
      $error = empty($error) ? 'Unknown error occurred' : $error;
      throw new \Exception($error);
    }
  }

  /**
   * Resolve and validate CLI options values into internal values.
   *
   * @param string $url
   *   Remote URL.
   * @param array<mixed> $options
   *   Array of CLI options.
   */
  protected function resolveOptions(string $url, array $options): void {
    if (!empty($options['root']) && is_scalar($options['root'])) {
      $this->fsSetRootDir(strval($options['root']));
    }

    $this->remoteUrl = $url;
    $this->remoteName = self::GIT_REMOTE_NAME;
    $this->now = empty($options['now']) ? time() : (int) $options['now'];
    $this->showChanges = !empty($options['show-changes']);
    $this->needCleanup = empty($options['no-cleanup']);
    $this->isDryRun = !empty($options['dry-run']);
    $this->logFile = empty($options['log']) ? '' : $this->fsGetAbsolutePath($options['log']);

    $this->setMode($options['mode'], $options);

    $this->sourceDir = empty($options['src']) ? $this->fsGetRootDir() : $this->fsGetAbsolutePath($options['src']);

    // Setup Git repository from source path.
    $this->repo = new ArtifactGitRepository($this->sourceDir, NULL, $this->logger);

    // Set original, destination, artifact branch names.
    $this->originalBranch = $this->repo->getOriginalBranch();

    $branch = $this->tokenProcess($options['branch']);
    if (!ArtifactGitRepository::isValidBranchName($branch)) {
      throw new \RuntimeException(sprintf('Incorrect value "%s" specified for git remote branch', $branch));
    }
    $this->destinationBranch = $branch;

    $this->artifactBranch = $this->destinationBranch . '-artifact';

    $this->commitMessage = $this->tokenProcess($options['message']);

    if (!empty($options['gitignore'])) {
      $gitignore = $this->fsGetAbsolutePath($options['gitignore']);
      $this->fsAssertPathsExist($gitignore);

      $contents = file_get_contents($gitignore);
      if (!$contents) {
        // @codeCoverageIgnoreStart
        throw new \Exception('Unable to load contents of ' . $gitignore);
        // @codeCoverageIgnoreEnd
      }

      $this->logger->debug('-----Custom .gitignore---------');
      $this->logger->debug($contents);
      $this->logger->debug('-----.gitignore---------');

      $this->gitignoreFile = $gitignore;
      $this->repo->setGitignoreCustom($this->gitignoreFile);
    }
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
    $lines[] = (' Source repository:     ' . $this->sourceDir);
    $lines[] = (' Remote repository:     ' . $this->remoteUrl);
    $lines[] = (' Remote branch:         ' . $this->destinationBranch);
    $lines[] = (' Gitignore file:        ' . ($this->gitignoreFile ?: 'No'));
    $lines[] = (' Will push:             ' . ($this->isDryRun ? 'No' : 'Yes'));
    $lines[] = ('----------------------------------------------------------------------');

    $this->output->writeln($lines);

    foreach ($lines as $line) {
      $this->logger->notice($line);
    }
  }

  /**
   * Dump artifact report to a file.
   */
  protected function showReport(bool $result): void {
    $lines[] = '----------------------------------------------------------------------';
    $lines[] = ' Artifact report';
    $lines[] = '----------------------------------------------------------------------';
    $lines[] = ' Build timestamp:   ' . date('Y/m/d H:i:s', $this->now);
    $lines[] = ' Mode:              ' . $this->mode;
    $lines[] = ' Source repository: ' . $this->sourceDir;
    $lines[] = ' Remote repository: ' . $this->remoteUrl;
    $lines[] = ' Remote branch:     ' . $this->destinationBranch;
    $lines[] = ' Gitignore file:    ' . ($this->gitignoreFile ?: 'No');
    $lines[] = ' Commit message:    ' . $this->commitMessage;
    $lines[] = ' Push result:       ' . ($result ? 'Success' : 'Failure');
    $lines[] = '----------------------------------------------------------------------';

    foreach ($lines as $line) {
      $this->logger->notice($line);
    }
  }

  /**
   * Set build mode.
   *
   * @param string $mode
   *   Mode to set.
   * @param array<mixed> $options
   *   Array of CLI options.
   */
  protected function setMode(string $mode, array $options): void {
    switch ($mode) {
      case self::MODE_FORCE_PUSH:
        // Intentionally empty.
        break;

      case self::MODE_BRANCH:
        if (is_scalar($options['branch'] ?? NULL) && !self::tokenExists(strval($options['branch']))) {
          $this->output->writeln('<comment>WARNING! Provided branch name does not have a token.
                    Pushing of the artifact into this branch will fail on second and follow-up pushes to remote.
                    Consider adding tokens with unique values to the branch name.</comment>');
        }
        break;

      default:
        throw new \RuntimeException(sprintf('Invalid mode provided. Allowed modes are: %s', implode(', ', [
          self::MODE_FORCE_PUSH,
          self::MODE_BRANCH,
        ])));
    }

    $this->mode = $mode;
  }

  /**
   * Check that there all requirements are met in order to to run this command.
   */
  protected function checkRequirements(): void {
    $this->logger->notice('Checking requirements');

    if (!$this->fsIsCommandAvailable('git')) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('Git command is not available');
      // @codeCoverageIgnoreEnd
    }

    $this->logger->notice('All requirements were met');
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
    return $this->repo->getCurrentBranchName();
  }

  /**
   * Token callback to get current branch as a safe string.
   *
   * @return string
   *   Branch name.
   *
   * @throws \Exception
   */
  protected function getTokenSafebranch(): string {
    $name = $this->repo->getCurrentBranchName();

    $replacement = preg_replace('/[^a-z0-9-]/i', '-', strtolower($name));

    if (empty($replacement)) {
      // @codeCoverageIgnoreStart
      throw new \Exception('Safe branch name is empty');
      // @codeCoverageIgnoreEnd
    }

    return $replacement;
  }

  /**
   * Token callback to get tags.
   *
   * @param string|null $delimiter
   *   Token delimiter. Defaults to '-'.
   *
   * @return string
   *   String of tags.
   *
   * @throws \Exception
   */
  protected function getTokenTags(?string $delimiter): string {
    $delimiter = $delimiter ?? '-';

    return implode($delimiter, $this->repo->listTagsPointingToHead());
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

}
