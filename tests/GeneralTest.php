<?php

namespace IntegratedExperts\Robo\Tests;

use IntegratedExperts\Robo\ArtefactTrait;

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
        $output = $this->runBuild();

        $this->assertContains(sprintf('Artefact information'), $output);
        $this->assertContains(sprintf('Mode:                  %s', ArtefactTrait::modeForcePush()), $output);
        $this->assertContains(sprintf('Source repository:     %s', $this->getFixtureSrcDir()), $output);
        $this->assertContains(sprintf('Remote repository:     %s', $this->getFixtureRemoteDir()), $output);
        $this->assertContains(sprintf('Remote branch:         %s', $this->defaultCurrentBranch), $output);
        $this->assertContains(sprintf('Gitignore file:        No'), $output);
        $this->assertContains(sprintf('Will push:             No'), $output);

        $this->assertContains('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);

        $this->gitAssertFilesNotExist($this->getFixtureRemoteDir(), '1.txt', $this->defaultCurrentBranch);
    }
}
