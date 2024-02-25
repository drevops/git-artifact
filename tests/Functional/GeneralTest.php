<?php

declare(strict_types = 1);

namespace DrevOps\Robo\Tests\Functional;

/**
 * Class GeneralTest.
 *
 * @group integration
 *
 * @covers \DrevOps\Robo\ArtefactTrait
 * @covers \DrevOps\Robo\FilesystemTrait
 */
class GeneralTest extends AbstractFunctionalTestCase
{

    public function testPresence(): void
    {
        $output = $this->runRoboCommand('list');
        $this->assertStringContainsString('artifact', implode(PHP_EOL, $output));
    }

    public function testHelp(): void
    {
        $output = $this->runRoboCommand('--help artifact');
        $this->assertStringContainsString('artifact [options] [--] <remote>', implode(PHP_EOL, $output));
    }

    public function testCompulsoryParameter(): void
    {
        $output = $this->runRoboCommand('artifact', true);

        $this->assertStringContainsString('Not enough arguments (missing: "remote")', implode(PHP_EOL, $output));
    }

    public function testInfo(): void
    {
        $this->gitCreateFixtureCommits(1);
        $output = $this->runBuild();

        $this->assertStringContainsString('Artefact information', $output);
        $this->assertStringContainsString('Mode:                  force-push', $output);
        $this->assertStringContainsString('Source repository:     '.$this->src, $output);
        $this->assertStringContainsString('Remote repository:     '.$this->dst, $output);
        $this->assertStringContainsString('Remote branch:         '.$this->currentBranch, $output);
        $this->assertStringContainsString('Gitignore file:        No', $output);
        $this->assertStringContainsString('Will push:             No', $output);
        $this->assertStringNotContainsString('Added changes:', $output);

        $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);

        $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
    }

    public function testShowChanges(): void
    {
        $this->gitCreateFixtureCommits(1);
        $output = $this->runBuild('--show-changes');

        $this->assertStringContainsString('Added changes:', $output);

        $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
        $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
    }

    public function testNoCleanup(): void
    {
        $this->gitCreateFixtureCommits(1);
        $output = $this->runBuild('--no-cleanup');

        $this->assertGitCurrentBranch($this->src, $this->artifactBranch);

        $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
        $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
    }

    public function testReport(): void
    {
        $report = $this->src.DIRECTORY_SEPARATOR.'report.txt';

        $this->gitCreateFixtureCommits(1);
        $this->runBuild(sprintf('--report=%s', $report));

        $this->assertFileExists($report);
        $output = file_get_contents($report);

        $this->assertStringContainsString('Artefact report', (string) $output);
        $this->assertStringContainsString(sprintf('Source repository: %s', $this->src), (string) $output);
        $this->assertStringContainsString(sprintf('Remote repository: %s', $this->dst), (string) $output);
        $this->assertStringContainsString(sprintf('Remote branch:     %s', $this->currentBranch), (string) $output);
        $this->assertStringContainsString('Gitignore file:    No', (string) $output);
        $this->assertStringContainsString('Push result:       Success', (string) $output);
    }

    public function testDebug(): void
    {
        $this->gitCreateFixtureCommits(1);
        $output = $this->runBuild('--debug');

        $this->assertStringContainsString('Debug messages enabled', $output);
        $this->assertStringContainsString('[Exec]', $output);

        $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
        $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
    }

    public function testDebugDisabled(): void
    {
        $this->gitCreateFixtureCommits(1);
        $output = $this->runBuild();

        $this->assertStringNotContainsString('Debug messages enabled', $output);
        $this->assertStringNotContainsString('[Exec]', $output);

        $this->assertStringContainsString('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);
        $this->gitAssertFilesNotExist($this->dst, 'f1', $this->currentBranch);
    }
}
