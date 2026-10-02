<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class GeneralTest extends FunctionalTestCase {

  public function testHelp(): void {
    $output = $this->runArtifactCommand(['--help' => TRUE]);

    $this->assertStringContainsString('artifact [options] [--] <remote>', $output);
    $this->assertStringContainsString('Assemble a code artifact from your codebase, remove unnecessary files, and push it into a separate Git repository.', $output);
    $this->assertStringContainsString('--source=SOURCE', $output);
    $this->assertStringContainsString('Deprecated alias of --source. Will be removed in a future major release.', $output);
  }

  public function testCompulsoryParameter(): void {
    $this->dst = '';
    $output = $this->assertArtifactCommandFailure(['remote' => ' ']);

    $this->assertStringContainsString('Remote argument must be a non-empty string.', $output);
  }

  public function testInfo(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->runArtifactCommand(['--dry-run' => TRUE]);

    $this->assertStringContainsString('Artifact information', $output);
    $this->assertStringContainsString('Mode:                  ' . ArtifactCommand::MODE_FORCE_PUSH, $output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, $output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, $output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, $output);
    $this->assertStringContainsString('Gitignore file:        No', $output);
    $this->assertStringContainsString('Will push:             No', $output);
    $this->assertStringNotContainsString('Added changes:', $output);
    $this->assertStringNotContainsString('Artifact report', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);

    $this->gitCheckout($this->dst, $this->currentBranch);
    $this->assertFilesNotExist($this->dst, 'f1');
  }

  public function testShowChanges(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->runArtifactCommand([
      '--show-changes' => TRUE,
      '--dry-run' => TRUE,
    ]);

    $this->assertStringContainsString('Added changes:', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->gitCheckout($this->dst, $this->currentBranch);
    $this->assertFilesNotExist($this->dst, 'f1');
  }

  public function testSourceDefault(): void {
    $this->gitCreateFixtureCommits(1);

    $old_fixture_dir = $this->fixtureDir;
    $this->fixtureDir = $this->src;

    $this->src = '';

    $output = $this->runArtifactCommand(['--dry-run' => TRUE]);

    $this->assertStringContainsString('Source repository:     ' . $this->fixtureDir, $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);

    $this->fixtureDir = $old_fixture_dir;
  }

  public function testSrcDeprecated(): void {
    $this->gitCreateFixtureCommits(1);

    $src = $this->src;
    // An empty $src stops runArtifactCommand() from adding --source.
    $this->src = '';

    $output = $this->assertArtifactCommandSuccess(['--src' => $src]);

    $this->assertSame(1, substr_count($output, 'The --src option is deprecated and will be removed in a future major release. Use --source instead.'));
    $this->assertStringContainsString('Source repository:     ' . $src, $output);

    $this->gitCheckout($this->dst, 'testbranch');
    $this->assertFilesExist($this->dst, 'f1');
  }

  public function testSourceSrcConflict(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->assertArtifactCommandFailure(['--src' => $this->src]);

    $this->assertStringContainsString('The --source and --src options cannot be used together.', $output);
  }

  public function testModeInvalid(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->assertArtifactCommandFailure(['--mode' => 'invalid']);

    $this->assertStringContainsString('Invalid mode provided. Allowed modes are: "force-push", "branch".', $output);
  }

  public function testRemoteInvalid(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->assertArtifactCommandFailure(['remote' => 'git://user/repo']);

    $this->assertStringContainsString('Invalid remote URL provided: "git://user/repo".', $output);
  }

  public function testNoCleanup(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->runArtifactCommand([
      '--no-cleanup' => TRUE,
      '--dry-run' => TRUE,
    ]);

    $this->gitAssertCurrentBranch($this->src, $this->artifactBranch);
    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->assertFilesNotExist($this->dst, 'f1');
  }

  public function testDebug(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->runArtifactCommand([
      '-vvv' => TRUE,
      '--dry-run' => TRUE,
    ]);

    $this->assertStringContainsString('Debug messages enabled.', $output);
    $this->assertStringContainsString('Checking requirements.', $output);
    $this->assertStringContainsString('All requirements were met.', $output);
    $this->assertStringContainsString('Artifact information', $output);
    $this->assertStringContainsString('Mode:                  ' . ArtifactCommand::MODE_FORCE_PUSH, $output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, $output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, $output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, $output);
    $this->assertStringContainsString('Gitignore file:        No', $output);
    $this->assertStringContainsString('Will push:             No', $output);

    $this->assertStringContainsString('Artifact report', $output);
    $this->assertStringContainsString('Commit message:        Deployment commit', $output);
    $this->assertStringContainsString('Push result:           Success', $output);
    $this->assertStringContainsString('Cleaning up.', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->gitCheckout($this->dst, $this->currentBranch);
    $this->assertFilesNotExist($this->dst, 'f1');
  }

  public function testDebugLogFile(): void {
    $report = $this->src . DIRECTORY_SEPARATOR . 'report.txt';

    $this->gitCreateFixtureCommits(1);
    $output = $this->runArtifactCommand([
      '--dry-run' => TRUE,
      '--log' => $report,
    ]);

    $this->assertStringContainsString('Debug messages enabled.', $output);
    $this->assertStringContainsString('Artifact information', $output);
    $this->assertStringContainsString('Mode:                  ' . ArtifactCommand::MODE_FORCE_PUSH, $output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, $output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, $output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, $output);
    $this->assertStringContainsString('Gitignore file:        No', $output);
    $this->assertStringContainsString('Will push:             No', $output);

    $this->assertStringContainsString('Artifact report', $output);
    $this->assertStringContainsString('Commit message:        Deployment commit', $output);
    $this->assertStringContainsString('Push result:           Success', $output);

    $this->assertFileExists($report);
    $report_output = file_get_contents($report);

    $this->assertStringContainsString('Debug messages enabled.', (string) $report_output);
    $this->assertStringContainsString('Artifact information', (string) $report_output);
    $this->assertStringContainsString('Mode:                  ' . ArtifactCommand::MODE_FORCE_PUSH, (string) $report_output);
    $this->assertStringContainsString('Source repository:     ' . $this->src, (string) $report_output);
    $this->assertStringContainsString('Remote repository:     ' . $this->dst, (string) $report_output);
    $this->assertStringContainsString('Remote branch:         ' . $this->currentBranch, (string) $report_output);
    $this->assertStringContainsString('Gitignore file:        No', (string) $report_output);
    $this->assertStringContainsString('Will push:             No', (string) $report_output);
    $this->assertStringNotContainsString('Added changes:', (string) $report_output);

    $this->assertStringContainsString('Artifact report', (string) $report_output);
    $this->assertStringContainsString('Commit message:        Deployment commit', (string) $report_output);
    $this->assertStringContainsString('Push result:           Success', (string) $report_output);

    $shared_rows = [
      ' Packaging timestamp:   ' . date('Y/m/d H:i:s', $this->now),
      ' Mode:                  ' . ArtifactCommand::MODE_FORCE_PUSH,
      ' Source repository:     ' . $this->src,
      ' Remote repository:     ' . $this->dst,
      ' Remote branch:         ' . $this->currentBranch,
      ' Gitignore file:        No',
    ];

    foreach ($shared_rows as $shared_row) {
      $this->assertSame(2, substr_count((string) $report_output, $shared_row), sprintf('Row "%s" is logged once by each block.', $shared_row));
    }
  }

  public function testDebugDisabled(): void {
    $this->gitCreateFixtureCommits(1);

    $output = $this->runArtifactCommand(['--dry-run' => TRUE]);

    $this->assertStringNotContainsString('Debug messages enabled', $output);

    $this->assertStringContainsString('Cowardly refusing to push to remote. Use without --dry-run to perform an actual push.', $output);
    $this->gitCheckout($this->dst, $this->currentBranch);
    $this->assertFilesNotExist($this->dst, 'f1');
  }

}
