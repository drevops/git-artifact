<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use CzProject\GitPhp\GitException;
use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(ArtifactCommand::class)]
#[CoversClass(ArtifactGitRepository::class)]
class ArtifactCommandTest extends UnitTestCase {

  public function testCleanupStaleBranchesHandlesListFailure(): void {
    $repo = $this->prepareMock(ArtifactGitRepository::class, [
      'getRemoteBranchesInfo' => fn(): never => throw new GitException('boom'),
    ], FALSE);

    $output = new BufferedOutput();
    $command = $this->createCleanupCommand($repo, $output);

    $this->callProtectedMethod($command, 'cleanupStaleBranches');

    $this->assertStringContainsString('Unable to list remote branches for cleanup.', $output->fetch());
  }

  public function testCleanupStaleBranchesHandlesDeleteFailure(): void {
    $repo = $this->prepareMock(ArtifactGitRepository::class, [
      'getRemoteBranchesInfo' => fn(): array => ['deployment/old' => 1000],
      'getRemoteDefaultBranch' => fn(): string => 'main',
      'deleteRemoteBranch' => fn(): never => throw new GitException('boom'),
    ], FALSE);

    $output = new BufferedOutput();
    $command = $this->createCleanupCommand($repo, $output);

    $this->callProtectedMethod($command, 'cleanupStaleBranches');

    $this->assertStringContainsString('Failed to delete stale branch "deployment/old"', $output->fetch());
  }

  public function testCleanupStaleBranchesSkipsWhenDefaultBranchUnknown(): void {
    $repo = $this->prepareMock(ArtifactGitRepository::class, [
      'getRemoteBranchesInfo' => fn(): array => ['deployment/old' => 1000],
      'getRemoteDefaultBranch' => fn(): ?string => NULL,
    ], FALSE);

    $output = new BufferedOutput();
    $command = $this->createCleanupCommand($repo, $output);

    $this->callProtectedMethod($command, 'cleanupStaleBranches');

    $this->assertStringContainsString('Unable to determine the remote default branch', $output->fetch());
  }

  /**
   * Build a command instance wired for cleanupStaleBranches() in isolation.
   *
   * The command's collaborators are normally populated by execute(); here they
   * are injected directly via reflection so cleanupStaleBranches() can be
   * exercised on its own, without bootstrapping the full command run.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject $repo
   *   Repository mock to operate on.
   * @param \Symfony\Component\Console\Output\BufferedOutput $output
   *   Output buffer to capture messages.
   *
   * @return \DrevOps\GitArtifact\Commands\ArtifactCommand
   *   Configured command instance.
   */
  protected function createCleanupCommand(MockObject $repo, BufferedOutput $output): ArtifactCommand {
    $command = new ArtifactCommand();

    $this->setProtectedValue($command, 'repo', $repo);
    $this->setProtectedValue($command, 'cleanupStale', TRUE);
    $this->setProtectedValue($command, 'cleanupPattern', '*');
    $this->setProtectedValue($command, 'cleanupAge', 3);
    $this->setProtectedValue($command, 'now', 100000000);
    $this->setProtectedValue($command, 'remoteName', 'dst');
    $this->setProtectedValue($command, 'destinationBranch', 'main');
    $this->setProtectedValue($command, 'isDryRun', FALSE);
    $this->setProtectedValue($command, 'output', $output);
    $this->setProtectedValue($command, 'logger', new NullLogger());

    return $command;
  }

}
