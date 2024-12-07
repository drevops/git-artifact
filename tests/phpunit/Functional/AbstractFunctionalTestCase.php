<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Tests\Traits\FixtureTrait;
use DrevOps\GitArtifact\Tests\Traits\GitTrait;
use DrevOps\GitArtifact\Tests\Unit\AbstractUnitTestCase;
use DrevOps\GitArtifact\Traits\FilesystemTrait;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractFunctionalTestCase extends AbstractUnitTestCase {

  use FilesystemTrait;
  use FixtureTrait;
  use GitTrait;

  /**
   * Fixture source repository directory.
   *
   * @var string
   */
  protected $src;

  /**
   * Fixture remote repository directory.
   *
   * @var string
   */
  protected $dst;

  /**
   * Artifact command.
   */
  protected ArtifactCommand $command;

  /**
   * Fixture directory.
   *
   * @var string
   */
  protected $fixtureDir;

  /**
   * Current branch.
   *
   * @var string
   */
  protected $currentBranch;

  /**
   * Artifact branch.
   *
   * @var string
   */
  protected $artifactBranch;

  /**
   * Remote name.
   *
   * @var string
   */
  protected $remoteName;

  /**
   * Mode in which the build will run.
   *
   * Passed as a value of the --mode option.
   *
   * @var string
   */
  protected $mode;

  /**
   * Current timestamp to run commands with.
   *
   * Used for generating internal tokens that could be based on time.
   *
   * @var int
   */
  protected $now;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fs = new Filesystem();

    $this->fixtureDir = $this->fsGetAbsolutePath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . date('U') . DIRECTORY_SEPARATOR . 'git_artifact');

    $this->src = $this->fsGetAbsolutePath($this->fixtureDir . DIRECTORY_SEPARATOR . 'src');
    $this->gitInitRepo($this->src);

    $this->dst = $this->fixtureDir . DIRECTORY_SEPARATOR . 'dst';
    $this->gitInitRepo($this->dst)
      // Allow pushing into already checked out branch. We need this to
      // avoid additional management of fixture repository.
      ->run('config', ['receive.denyCurrentBranch', 'ignore']);

    $this->now = time();
    $this->currentBranch = $this->gitGetGlobalDefaultBranch();
    $this->artifactBranch = $this->currentBranch . '-artifact';
    $this->remoteName = 'dst';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->fs->exists($this->fixtureDir)) {
      $this->fs->remove($this->fixtureDir);
    }
  }

  /**
   * Build the artifact and assert success.
   *
   * @param array $args
   *   Array of arguments to pass to the build.
   * @param string $branch
   *   Expected branch name.
   * @param string $commit
   *   Optional commit string. Defaults to 'Deployment commit'.
   *
   * @return string
   *   Command output.
   */
  protected function assertCommandSuccess(?array $args = [], string $branch = 'testbranch', string $commit = 'Deployment commit'): string {
    $args += ['--branch' => 'testbranch'];
    $output = $this->runCommand($args);

    $this->assertStringNotContainsString('[error]', $output);
    $this->assertStringContainsString(sprintf('Pushed branch "%s" with commit message "%s"', $branch, $commit), $output);
    $this->assertStringContainsString('Deployment finished successfully.', $output);
    $this->assertStringNotContainsString('Processing failed with an error:', $output);

    return $output;
  }

  /**
   * Build the artifact and assert failure.
   *
   * @param array $args
   *   *   Array of arguments to pass to the build.
   *   * @param string $branch
   *   *   Expected branch name.
   * @param string $commit
   *   Optional commit string. Defaults to 'Deployment commit'.
   *
   * @return string
   *   Command output.
   */
  protected function assertCommandFailure(?array $args = [], string $commit = 'Deployment commit'): string {
    $args += ['--branch' => 'testbranch'];
    $output = $this->runCommand($args, TRUE);

    $this->assertStringNotContainsString(sprintf('Pushed branch "%s" with commit message "%s"', $args['--branch'], $commit), $output);
    $this->assertStringNotContainsString('Deployment finished successfully.', $output);
    $this->assertStringContainsString('Processing failed with an error:', $output);

    return $output;
  }

  /**
   * Run artifact build.
   *
   * @param array $args
   *   Additional arguments or options as an associative array.
   * @param bool $expect_fail
   *   Expect on fail.
   *
   * @return string
   *   Output string.
   */
  protected function runCommand(?array $args = [], bool $expect_fail = FALSE): string {
    try {

      if (is_null($args)) {
        $input = [];
      }
      else {
        $input = [
          '--root' => $this->fixtureDir,
          '--now' => $this->now,
          '--src' => $this->src,
          'remote' => $this->dst,
        ];

        if ($this->mode) {
          $input['--mode'] = $this->mode;
        }

        $input += $args;
      }

      $this->runExecute(ArtifactCommand::class, $input);
      $output = $this->commandTester->getDisplay();

      if ($this->commandTester->getStatusCode() !== 0) {
        throw new \Exception(sprintf("Command exited with non-zero code.\nThe output was:\n%s\nThe error output was:\n%s", $this->commandTester->getDisplay(), $this->commandTester->getErrorOutput()));
      }

      if ($expect_fail) {
        throw new AssertionFailedError(sprintf("Command exited successfully but should not.\nThe output was:\n%s\nThe error output was:\n%s", $this->commandTester->getDisplay(), $this->commandTester->getErrorOutput()));
      }

    }
    catch (\RuntimeException $exception) {
      if (!$expect_fail) {
        throw new AssertionFailedError('Command exited with an error:' . PHP_EOL . $exception->getMessage());
      }
      $output = $exception->getMessage();
    }
    catch (\Exception $exception) {
      if (!$expect_fail) {
        throw new AssertionFailedError('Command exited with an error:' . PHP_EOL . $exception->getMessage());
      }
    }

    return $output;
  }

  /**
   * CommandTester instance.
   *
   * @var \Symfony\Component\Console\Tester\CommandTester
   */
  protected $commandTester;

  /**
   * Run main() with optional arguments.
   *
   * @param string|object $object_or_class
   *   Object or class name.
   * @param array<string> $input
   *   Optional array of input arguments.
   * @param array<string, string> $options
   *   Optional array of options. See CommandTester::execute() for details.
   */
  protected function runExecute(string|object $object_or_class, array $input = [], array $options = []): void {
    $application = new Application();
    /** @var \Symfony\Component\Console\Command\Command $instance */
    $instance = is_object($object_or_class) ? $object_or_class : new $object_or_class();
    $application->add($instance);

    $name = $instance->getName();
    if (empty($name)) {
      /** @var string $name */
      $name = $this->getProtectedValue($instance, 'defaultName');
    }

    $command = $application->find($name);
    $this->commandTester = new CommandTester($command);

    $options['capture_stderr_separately'] = TRUE;
    if (array_key_exists('-vvv', $input)) {
      $options['verbosity'] = ConsoleOutput::VERBOSITY_DEBUG;
      unset($input['-vvv']);
    }

    $this->commandTester->execute($input, $options);
  }

}
