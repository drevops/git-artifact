<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Commands;

use CzProject\GitPhp\GitException;
use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use DrevOps\GitArtifact\Tests\Unit\UnitTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
      'removeRemoteBranch' => fn(): never => throw new GitException('boom'),
    ], FALSE);

    $output = new BufferedOutput();
    $command = $this->createCleanupCommand($repo, $output);

    $this->callProtectedMethod($command, 'cleanupStaleBranches');

    $this->assertStringContainsString('Failed to delete stale branch "deployment/old".', $output->fetch());
  }

  public function testCleanupStaleBranchesSkipsWhenDefaultBranchUnknown(): void {
    $repo = $this->prepareMock(ArtifactGitRepository::class, [
      'getRemoteBranchesInfo' => fn(): array => ['deployment/old' => 1000],
      'getRemoteDefaultBranch' => fn(): ?string => NULL,
    ], FALSE);

    $output = new BufferedOutput();
    $command = $this->createCleanupCommand($repo, $output);

    $this->callProtectedMethod($command, 'cleanupStaleBranches');

    $this->assertStringContainsString('Unable to determine the remote default branch; skipping stale cleanup for safety.', $output->fetch());
  }

  #[DataProvider('dataProviderFormatSummary')]
  public function testFormatSummary(array $rows, array $expected_rows): void {
    $actual = $this->callProtectedMethod(ArtifactCommand::class, 'formatSummary', ['Title', $rows]);

    $separator = str_repeat('-', 70);
    $this->assertSame([$separator, ' Title', $separator, ...$expected_rows, $separator], $actual);
  }

  public static function dataProviderFormatSummary(): array {
    return [
      'no rows' => [[], []],
      'short label' => [['Mode' => 'branch'], [' Mode:                  branch']],
      'label at the width' => [['Twenty one characters' => 'value'], [' Twenty one characters: value']],
      'label beyond the width' => [['Label well beyond the width' => 'value'], [' Label well beyond the width: value']],
      'empty value' => [['Commit message' => ''], [' Commit message:        ']],
      'rows in given order' => [['B' => '2', 'A' => '1'], [' B:                     2', ' A:                     1']],
    ];
  }

  #[DataProvider('dataProviderShowInfo')]
  public function testShowInfo(array $values, array $expected_rows): void {
    $output = new BufferedOutput();
    $handler = new TestHandler();
    $command = $this->createSummaryCommand($output, $handler, $values);

    $this->callProtectedMethod($command, 'showInfo');

    $separator = str_repeat('-', 70);
    $expected = [$separator, ' Artifact information', $separator, ...$expected_rows, $separator];

    $this->assertSame(implode(PHP_EOL, $expected) . PHP_EOL, $output->fetch());
    $this->assertSame($expected, $this->getLoggedMessages($handler));
  }

  public static function dataProviderShowInfo(): array {
    $timestamp = ' Packaging timestamp:   ' . date('Y/m/d H:i:s', 100000000);

    return [
      'push' => [
        [],
        [
          $timestamp,
          ' Mode:                  force-push',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        No',
          ' Will push:             Yes',
        ],
      ],
      'dry run' => [
        ['isDryRun' => TRUE],
        [
          $timestamp,
          ' Mode:                  force-push',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        No',
          ' Will push:             No',
        ],
      ],
      'branch mode with custom gitignore' => [
        ['mode' => ArtifactCommand::MODE_BRANCH, 'gitignoreCustom' => '/path/to/.gitignore.artifact'],
        [
          $timestamp,
          ' Mode:                  branch',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        /path/to/.gitignore.artifact',
          ' Will push:             Yes',
        ],
      ],
      'cleanup with 1 pattern' => [
        ['cleanupStale' => TRUE, 'cleanupPatterns' => ['deployment/*']],
        [
          $timestamp,
          ' Mode:                  force-push',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        No',
          ' Will push:             Yes',
          ' Cleanup stale:         Yes (pattern "deployment/*", older than 3 days)',
        ],
      ],
      'cleanup with patterns' => [
        ['cleanupStale' => TRUE, 'cleanupPatterns' => ['feature/*', 'bugfix/*']],
        [
          $timestamp,
          ' Mode:                  force-push',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        No',
          ' Will push:             Yes',
          ' Cleanup stale:         Yes (patterns "feature/*", "bugfix/*", older than 3 days)',
        ],
      ],
    ];
  }

  #[DataProvider('dataProviderShowReport')]
  public function testShowReport(bool $result, array $values, array $expected_rows): void {
    $output = new BufferedOutput();
    $handler = new TestHandler();
    $command = $this->createSummaryCommand($output, $handler, $values);

    $this->callProtectedMethod($command, 'showReport', [$result]);

    $separator = str_repeat('-', 70);
    $expected = [$separator, ' Artifact report', $separator, ...$expected_rows, $separator];

    $this->assertSame('', $output->fetch());
    $this->assertSame($expected, $this->getLoggedMessages($handler));
  }

  public static function dataProviderShowReport(): array {
    $timestamp = ' Packaging timestamp:   ' . date('Y/m/d H:i:s', 100000000);

    return [
      'success' => [
        TRUE,
        [],
        [
          $timestamp,
          ' Mode:                  force-push',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        No',
          ' Commit message:        Deployment commit',
          ' Push result:           Success',
        ],
      ],
      'failure' => [
        FALSE,
        [],
        [
          $timestamp,
          ' Mode:                  force-push',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        No',
          ' Commit message:        Deployment commit',
          ' Push result:           Failure',
        ],
      ],
      'branch mode with custom gitignore and message' => [
        TRUE,
        ['mode' => ArtifactCommand::MODE_BRANCH, 'gitignoreCustom' => '/path/to/.gitignore.artifact', 'commitMessage' => 'Release 1.2.3'],
        [
          $timestamp,
          ' Mode:                  branch',
          ' Source repository:     /path/to/src',
          ' Remote repository:     /path/to/dst',
          ' Remote branch:         main',
          ' Gitignore file:        /path/to/.gitignore.artifact',
          ' Commit message:        Release 1.2.3',
          ' Push result:           Success',
        ],
      ],
    ];
  }

  public function testSummaryBlocksShareRows(): void {
    $handler = new TestHandler();
    $command = $this->createSummaryCommand(new BufferedOutput(), $handler, [
      'mode' => ArtifactCommand::MODE_BRANCH,
      'gitignoreCustom' => '/path/to/.gitignore.artifact',
    ]);

    $this->callProtectedMethod($command, 'showInfo');
    $info = $this->getLoggedMessages($handler);
    $handler->clear();

    $this->callProtectedMethod($command, 'showReport', [TRUE]);
    $report = $this->getLoggedMessages($handler);

    // Skip the separator, title and separator that open each block.
    $this->assertSame(array_slice($info, 3, 6), array_slice($report, 3, 6));
  }

  #[DataProvider('dataProviderResolveSourceDir')]
  public function testResolveSourceDir(array $options, string $expected, bool $is_deprecated): void {
    $output = new BufferedOutput();
    $handler = new TestHandler();
    $command = $this->createSourceCommand($output, $handler);

    $actual = $this->callProtectedMethod($command, 'resolveSourceDir', [$options]);

    $this->assertSame($expected, $actual);

    $notice = 'The --src option is deprecated and will be removed in a future major release. Use --source instead.';
    $this->assertSame($is_deprecated ? $notice . PHP_EOL : '', $output->fetch());
    $this->assertSame($is_deprecated ? [$notice] : [], $this->getLoggedMessages($handler));
  }

  public static function dataProviderResolveSourceDir(): array {
    return [
      'no options' => [[], '/path/to/root', FALSE],
      'unset options' => [['source' => NULL, 'src' => NULL], '/path/to/root', FALSE],
      'empty source' => [['source' => ''], '/path/to/root', FALSE],
      'relative source' => [['source' => 'src'], '/path/to/root/src', FALSE],
      'absolute source' => [['source' => '/path/to/src'], '/path/to/src', FALSE],
      'relative src' => [['src' => 'src'], '/path/to/root/src', TRUE],
      'absolute src' => [['src' => '/path/to/src'], '/path/to/src', TRUE],
      'source with empty src' => [['source' => 'src', 'src' => ''], '/path/to/root/src', FALSE],
      'src with empty source' => [['source' => '', 'src' => 'src'], '/path/to/root/src', TRUE],
    ];
  }

  #[DataProvider('dataProviderResolveSourceDirConflict')]
  public function testResolveSourceDirConflict(array $options): void {
    $command = $this->createSourceCommand(new BufferedOutput(), new TestHandler());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The --source and --src options cannot be used together.');

    $this->callProtectedMethod($command, 'resolveSourceDir', [$options]);
  }

  public static function dataProviderResolveSourceDirConflict(): array {
    return [
      'different values' => [['source' => 'src', 'src' => 'other']],
      'identical values' => [['source' => 'src', 'src' => 'src']],
    ];
  }

  /**
   * Build a command instance wired for cleanupStaleBranches() in isolation.
   *
   * The command's collaborators are normally populated by execute(). This
   * helper injects them via reflection, so cleanupStaleBranches() can be
   * called without running the whole command.
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
    $this->setProtectedValue($command, 'cleanupPatterns', ['*']);
    $this->setProtectedValue($command, 'cleanupAge', 3);
    $this->setProtectedValue($command, 'now', 100000000);
    $this->setProtectedValue($command, 'remoteName', 'dst');
    $this->setProtectedValue($command, 'destinationBranch', 'main');
    $this->setProtectedValue($command, 'isDryRun', FALSE);
    $this->setProtectedValue($command, 'output', $output);
    $this->setProtectedValue($command, 'logger', new NullLogger());

    return $command;
  }

  /**
   * Build a command instance wired for the summary blocks in isolation.
   *
   * @param \Symfony\Component\Console\Output\BufferedOutput $output
   *   Output buffer to capture console lines.
   * @param \Monolog\Handler\TestHandler $handler
   *   Log handler to capture logged messages.
   * @param array<string, mixed> $values
   *   Property values keyed by property name, overriding the defaults.
   *
   * @return \DrevOps\GitArtifact\Commands\ArtifactCommand
   *   Configured command instance.
   */
  protected function createSummaryCommand(BufferedOutput $output, TestHandler $handler, array $values = []): ArtifactCommand {
    $command = new ArtifactCommand();

    $values += [
      'now' => 100000000,
      'mode' => ArtifactCommand::MODE_FORCE_PUSH,
      'sourceDir' => '/path/to/src',
      'remoteUrl' => '/path/to/dst',
      'destinationBranch' => 'main',
      'gitignoreCustom' => NULL,
      'isDryRun' => FALSE,
      'cleanupStale' => FALSE,
      'cleanupPatterns' => [],
      'cleanupAge' => 3,
      'commitMessage' => 'Deployment commit',
      'output' => $output,
      'logger' => new Logger('artifact', [$handler]),
    ];

    foreach ($values as $property => $value) {
      $this->setProtectedValue($command, $property, $value);
    }

    return $command;
  }

  /**
   * Build a command instance wired for resolveSourceDir() in isolation.
   *
   * @param \Symfony\Component\Console\Output\BufferedOutput $output
   *   Output buffer to capture console lines.
   * @param \Monolog\Handler\TestHandler $handler
   *   Log handler to capture logged messages.
   *
   * @return \DrevOps\GitArtifact\Commands\ArtifactCommand
   *   Configured command instance with the root directory "/path/to/root".
   */
  protected function createSourceCommand(BufferedOutput $output, TestHandler $handler): ArtifactCommand {
    $command = new ArtifactCommand();

    $this->setProtectedValue($command, 'fsRootDir', '/path/to/root');
    $this->setProtectedValue($command, 'output', $output);
    $this->setProtectedValue($command, 'logger', new Logger('artifact', [$handler]));

    return $command;
  }

  /**
   * Get the messages captured by a log handler, in logging order.
   *
   * @param \Monolog\Handler\TestHandler $handler
   *   Log handler to read the records from.
   *
   * @return array<string>
   *   Logged messages.
   */
  protected function getLoggedMessages(TestHandler $handler): array {
    return array_map(static fn(LogRecord $record): string => $record->message, $handler->getRecords());
  }

}
