<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

/**
 * Class GeneralTest.
 *
 * @group integration
 *
 * @covers \DrevOps\GitArtifact\Commands\ArtifactCommand
 * @covers \DrevOps\GitArtifact\Traits\FilesystemTrait
 */
class GeneralTest extends AbstractFunctionalTestCase {

  public function testHelp(): void {
    $output = $this->runGitArtifactCommand('--help');
    $this->assertStringContainsString('artifact [options] [--] <remote>', implode(PHP_EOL, $output));
    $this->assertStringContainsString('Assemble a code artifact from your codebase, remove unnecessary files, and push it into a separate Git repository.', implode(PHP_EOL, $output));
  }

  public function testCompulsoryParameter(): void {
    $output = $this->runGitArtifactCommand('', TRUE);

    $this->assertStringContainsString('Not enough arguments (missing: "remote")', implode(PHP_EOL, $output));
  }

  public function testInfo(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runBuild();
    $this->assertStringContainsString('Artifact information', $output);
    $this->assertStringContainsString('Mode:                  force-push', $output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, $output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, $output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, $output);
    $this->assertStringContainsString('Gitignore file:        No', $output);
    $this->assertStringContainsString('Will push:             No', $output);
    $this->assertStringNotContainsString('Added changes:', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);

    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testShowChanges(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runBuild('--show-changes');

    $this->assertStringContainsString('Added changes:', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testNoCleanup(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runBuild('--no-cleanup');

    $this->assertGitCurrentBranch($this->src, $this->artifactBranch);
    $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testDebug(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runBuild('-vvv');

    $this->assertStringContainsString('Debug messages enabled', $output);
    $this->assertStringContainsString('Artifact information', $output);
    $this->assertStringContainsString('Mode:                  force-push', $output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, $output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, $output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, $output);
    $this->assertStringContainsString('Gitignore file:        No', $output);
    $this->assertStringContainsString('Will push:             No', $output);

    $this->assertStringContainsString('Artifact report', $output);
    $this->assertStringContainsString(sprintf('Source repository: %s', $this->src), $output);
    $this->assertStringContainsString(sprintf('Remote repository: %s', $this->dst), $output);
    $this->assertStringContainsString(sprintf('Remote branch:     %s', $this->currentBranch), $output);
    $this->assertStringContainsString('Gitignore file:    No', $output);
    $this->assertStringContainsString('Push result:       Success', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testDebugLogFile(): void {
    $report = $this->src . DIRECTORY_SEPARATOR . 'report.txt';

    $this->gitCreateFixtureCommits(1);
    $commandOutput = $this->runBuild(sprintf('--log=%s', $report));

    $this->assertStringContainsString('Debug messages enabled', $commandOutput);
    $this->assertStringContainsString('Artifact information', $commandOutput);
    $this->assertStringContainsString('Mode:                  force-push', $commandOutput);
    $this->assertStringContainsString('Source repository:     ' . $this->src, $commandOutput);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, $commandOutput);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, $commandOutput);
    $this->assertStringContainsString('Gitignore file:        No', $commandOutput);
    $this->assertStringContainsString('Will push:             No', $commandOutput);

    $this->assertStringContainsString('Artifact report', $commandOutput);
    $this->assertStringContainsString(sprintf('Source repository: %s', $this->src), $commandOutput);
    $this->assertStringContainsString(sprintf('Remote repository: %s', $this->dst), $commandOutput);
    $this->assertStringContainsString(sprintf('Remote branch:     %s', $this->currentBranch), $commandOutput);
    $this->assertStringContainsString('Gitignore file:    No', $commandOutput);
    $this->assertStringContainsString('Push result:       Success', $commandOutput);

    $this->assertFileExists($report);
    $output = file_get_contents($report);

    $this->assertStringContainsString('Debug messages enabled', (string) $output);
    $this->assertStringContainsString('Artifact information', (string) $output);
    $this->assertStringContainsString('Mode:                  force-push', (string) $output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, (string) $output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, (string) $output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, (string) $output);
    $this->assertStringContainsString('Gitignore file:        No', (string) $output);
    $this->assertStringContainsString('Will push:             No', (string) $output);
    $this->assertStringNotContainsString('Added changes:', (string) $output);

    $this->assertStringContainsString('Artifact report', (string) $output);
    $this->assertStringContainsString(sprintf('Source repository: %s', $this->src), (string) $output);
    $this->assertStringContainsString(sprintf('Remote repository: %s', $this->dst), (string) $output);
    $this->assertStringContainsString(sprintf('Remote branch:     %s', $this->currentBranch), (string) $output);
    $this->assertStringContainsString('Gitignore file:    No', (string) $output);
    $this->assertStringContainsString('Push result:       Success', (string) $output);
  }

  public function testDebugDisabled(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runBuild();

    $this->assertStringNotContainsString('Debug messages enabled', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

}
