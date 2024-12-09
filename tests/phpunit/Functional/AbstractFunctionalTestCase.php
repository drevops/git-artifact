<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Tests\Traits\ConsoleTrait;
use DrevOps\GitArtifact\Tests\Traits\FixtureTrait;
use DrevOps\GitArtifact\Tests\Traits\GitTrait;
use DrevOps\GitArtifact\Tests\Unit\AbstractUnitTestCase;
use DrevOps\GitArtifact\Traits\FilesystemTrait;
use Symfony\Component\Filesystem\Filesystem;

abstract class AbstractFunctionalTestCase extends AbstractUnitTestCase {

  use ConsoleTrait;
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
   * Remote name.
   */
  protected string $remoteName;

  /**
   * Current branch.
   */
  protected string $currentBranch;

  /**
   * Artifact branch.
   */
  protected string $artifactBranch;

  /**
   * Mode in which the artifact application will run.
   */
  protected ?string $mode = NULL;

  /**
   * Current timestamp to run commands with.
   *
   * Used for generating internal tokens that could be based on time.
   */
  protected int $now;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fs = new Filesystem();

    $this->fixtureInit('git_artifact');
    $this->fixtureDir = $this->fsGetAbsolutePath($this->fixtureDir);

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

    $this->consoleInitApplicationTester(ArtifactCommand::class);
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
  protected function assertArtifactCommandSuccess(?array $args = [], string $branch = 'testbranch', string $commit = 'Deployment commit'): string {
    $args += ['--branch' => 'testbranch'];

    $output = $this->runArtifactCommand($args);

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
  protected function assertArtifactCommandFailure(?array $args = [], string $commit = 'Deployment commit'): string {
    $args += ['--branch' => 'testbranch'];

    $output = $this->runArtifactCommand($args, TRUE);

    $this->assertStringNotContainsString(sprintf('Pushed branch "%s" with commit message "%s"', $args['--branch'], $commit), $output);
    $this->assertStringNotContainsString('Deployment finished successfully.', $output);
    $this->assertStringContainsString('Processing failed with an error:', $output);

    return $output;
  }

  /**
   * Run artifact build.
   *
   * @param array $args
   *   Additional arguments or options as an associative array. If NULL, no
   *   additional arguments are passed.
   * @param bool $expect_fail
   *   Expect on fail.
   *
   * @return string
   *   Output string.
   */
  protected function runArtifactCommand(?array $args = [], bool $expect_fail = FALSE): string {
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

    return $this->consoleApplicationRun($input, [], $expect_fail);
  }

  /**
   * Assert that files exist.
   *
   * @param string $path
   *   Repository location.
   * @param array<string>|string $files
   *   File or array of files.
   */
  protected function assertFilesExist(string $path, array|string $files): void {
    $files = is_array($files) ? $files : [$files];

    foreach ($files as $file) {
      $this->assertFileExists($path . DIRECTORY_SEPARATOR . $file);
    }
  }

  /**
   * Assert that files do not exist.
   *
   * @param string $path
   *   Repository location.
   * @param array<string>|string $files
   *   File or array of files.
   */
  protected function assertFilesNotExist(string $path, array|string $files): void {
    $files = is_array($files) ? $files : [$files];

    foreach ($files as $file) {
      $this->assertFileDoesNotExist($path . DIRECTORY_SEPARATOR . $file);
    }
  }

}
