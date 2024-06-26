<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Tests\AbstractTestCase;

/**
 * Class AbstractTestCase.
 */
abstract class AbstractFunctionalTestCase extends AbstractTestCase {

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
  protected $remote;

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

    $this->now = time();
    $this->currentBranch = 'master';
    $this->artifactBranch = 'master-artifact';
    $this->remote = 'dst';
  }

  /**
   * Build the artifact and assert success.
   *
   * @param string $args
   *   Optional string of arguments to pass to the build.
   * @param string $branch
   *   Optional --branch value. Defaults to 'testbranch'.
   * @param string $commit
   *   Optional commit string. Defaults to 'Deployment commit'.
   *
   * @return string
   *   Command output.
   */
  protected function assertBuildSuccess(string $args = '', string $branch = 'testbranch', string $commit = 'Deployment commit'): string {
    $output = $this->runBuild(sprintf('--branch=%s %s', $branch, $args));
    $this->assertStringNotContainsString('[error]', $output);
    $this->assertStringContainsString(sprintf('Pushed branch "%s" with commit message "%s"', $branch, $commit), $output);
    $this->assertStringContainsString('Deployment finished successfully.', $output);
    $this->assertStringNotContainsString('Deployment failed.', $output);

    return $output;
  }

  /**
   * Build the artifact and assert failure.
   *
   * @param string $args
   *   Optional string of arguments to pass to the build.
   * @param string $branch
   *   Optional --branch value. Defaults to 'testbranch'.
   * @param string $commit
   *   Optional commit string. Defaults to 'Deployment commit'.
   *
   * @return string
   *   Command output.
   */
  protected function assertBuildFailure(string $args = '', string $branch = 'testbranch', string $commit = 'Deployment commit'): string {
    $output = $this->runBuild(sprintf('--branch=%s %s', $branch, $args), TRUE);
    $this->assertStringNotContainsString(sprintf('Pushed branch "%s" with commit message "%s"', $branch, $commit), $output);
    $this->assertStringNotContainsString('Deployment finished successfully.', $output);
    $this->assertStringContainsString('Deployment failed.', $output);

    return $output;
  }

  /**
   * Run artifact build.
   *
   * @param string $args
   *   Additional arguments or options as a string.
   * @param bool $expectFail
   *   Expect on fail.
   *
   * @return string
   *   Output string.
   */
  protected function runBuild(string $args = '', bool $expectFail = FALSE): string {
    if ($this->mode) {
      $args .= ' --mode=' . $this->mode;
    }

    $output = $this->runGitArtifactCommandTimestamped(sprintf('--src=%s %s %s', $this->src, $this->dst, $args), $expectFail);

    return implode(PHP_EOL, $output);
  }

  /**
   * Run command with current timestamp attached to artifact commands.
   *
   * @param string $command
   *   Command string to run.
   * @param bool $expectFail
   *   Flag to state that the command should fail.
   *
   * @return array<string>
   *   Array of output lines.
   */
  protected function runGitArtifactCommandTimestamped(string $command, bool $expectFail = FALSE): array {
    // Add --now option to all 'artifact' commands.
    $command .= ' --now=' . $this->now;

    return $this->commandRunGitArtifactCommand($command, $expectFail);
  }

  /**
   * Assert current git branch.
   *
   * @param string $path
   *   Path to repository.
   * @param string $branch
   *   Branch name to assert.
   */
  protected function assertGitCurrentBranch(string $path, string $branch): void {
    $currentBranch = $this->git->open($path)->getCurrentBranchName();

    $this->assertStringContainsString($branch, $currentBranch, sprintf('Current branch is "%s"', $branch));
  }

  /**
   * Assert that there is no remote specified in git repository.
   *
   * @param string $path
   *   Path to repository.
   * @param string $remote
   *   Remote name to assert.
   */
  protected function assertGitNoRemote(string $path, string $remote): void {
    $remotes = $this->git->open($path)->getRemotes();
    if (empty($remotes)) {
      $this->assertEmpty($remotes);
    }
    else {
      $this->assertStringNotContainsString($remote, implode("\n", $remotes), sprintf('Remote "%s" is not present"', $remote));
    }
  }

}
