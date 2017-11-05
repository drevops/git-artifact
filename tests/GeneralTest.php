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
        $this->gitCreateFixtureCommits(1, $this->getFixtureSrcDir());
        $output = $this->runRoboCommand(sprintf('artefact --src=%s %s', $this->getFixtureSrcDir(), $this->getFixtureRemoteDir()));
        $output = implode(PHP_EOL, $output);

        // Assert information is correct.
        $this->assertContains('Artefact information', $output);
        $this->assertContains('Source repository:     '.$this->getFixtureSrcDir(), $output);
        $this->assertContains('Remote repository:     '.$this->getFixtureRemoteDir(), $output);
        $this->assertContains(sprintf('Remote branch:         %s', $this->defaultCurrentBranch), $output);
        $this->assertContains('Gitignore file:        No', $output);
        $this->assertContains('Will push:             No', $output);
        $this->assertContains(' Will force-push:       No', $output);

        $this->assertContains('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);

        $this->gitAssertFilesNotExist($this->getFixtureRemoteDir(), '1.txt', $this->defaultCurrentBranch);
    }
}
