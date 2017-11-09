<?php

namespace IntegratedExperts\Robo\Tests;

/**
 * Class GeneralTest.
 */
class GeneralTest extends AbstractTest
{

    public function testPresence()
    {
        $output = $this->runRoboCommand('list');
        $this->assertContains('artefact', implode(PHP_EOL, $output));
    }

    public function testHelp()
    {
        $output = $this->runRoboCommand('--help artefact');
        $this->assertContains('artefact [options] [--] <remote>', implode(PHP_EOL, $output));
    }

    public function testCompulsoryParameter()
    {
        $output = $this->runRoboCommand('artefact', true);

        $this->assertContains('Not enough arguments (missing: "remote")', implode(PHP_EOL, $output));
    }

    public function testInfo()
    {
        $this->gitCreateFixtureCommits(1);
        $output = $this->runBuild();

        $this->assertContains('Artefact information', $output);
        $this->assertContains('Mode:                  force-push', $output);
        $this->assertContains('Source repository:     '.$this->src, $output);
        $this->assertContains('Remote repository:     '.$this->dst, $output);
        $this->assertContains('Remote branch:         '.$this->currentBranch, $output);
        $this->assertContains('Gitignore file:        No', $output);
        $this->assertContains('Will push:             No', $output);

        $this->assertContains('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);

        $this->gitAssertFilesNotExist($this->dst, '1.txt', $this->currentBranch);
    }

    public function testReport()
    {
        $report = $this->src.DIRECTORY_SEPARATOR.'report.txt';

        $this->gitCreateFixtureCommits(1);
        $this->runBuild(sprintf('--report=%s', $report));

        $this->assertFileExists($report);
        $output = file_get_contents($report);

        $this->assertContains('Artefact report', $output);
        $this->assertContains(sprintf('Source repository: %s', $this->src), $output);
        $this->assertContains(sprintf('Remote repository: %s', $this->dst), $output);
        $this->assertContains(sprintf('Remote branch:     %s', $this->currentBranch), $output);
        $this->assertContains('Gitignore file:    No', $output);
        $this->assertContains('Push result:       Success', $output);
    }
}
