<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class GeneralTest extends AbstractFunctionalTestCase {

  public function testCompulsoryParameter(): void {
    $this->dst = '';
    $output = $this->runCommand(['remote' => ' '], TRUE);

    $this->assertStringContainsString('Remote argument must be a non-empty string', $output);
  }

  public function testInfo(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runCommand(['--dry-run' => TRUE]);
    $this->assertStringContainsString('Artifact information', $output);
    $this->assertStringContainsString('Mode:                  force-push', $output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, $output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, $output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, $output);
    $this->assertStringContainsString('Gitignore file:        No', $output);
    $this->assertStringContainsString('Will push:             No', $output);
    $this->assertStringNotContainsString('Added changes:', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);

    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testShowChanges(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runCommand([
      '--show-changes' => TRUE,
      '--dry-run' => TRUE,
    ]);

    $this->assertStringContainsString('Added changes:', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testNoCleanup(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runCommand([
      '--no-cleanup' => TRUE,
      '--dry-run' => TRUE,
    ]);

    $this->gitAssertCurrentBranch($this->src, $this->artifactBranch);
    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testDebug(): void {
    $this->gitCreateFixtureCommits(1);
    $output = $this->runCommand([
      '-vvv' => TRUE,
      '--dry-run' => TRUE,
    ]);

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

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

  public function testDebugLogFile(): void {
    $report = $this->src . DIRECTORY_SEPARATOR . 'report.txt';

    $this->gitCreateFixtureCommits(1);
    $commandOutput = $this->runCommand([
      '--dry-run' => TRUE,
      '--log' => $report,
    ]);

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
    $output = $this->runCommand(['--dry-run' => TRUE]);

    $this->assertStringNotContainsString('Debug messages enabled', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
  }

}
