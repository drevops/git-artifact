<?php

namespace IntegratedExperts\Robo\Tests;

/**
 * Class ForcePushTest.
 */
class ForcePushTest extends AbstractTest
{

    public function testForcePush()
    {
        $this->gitCreateFixtureFile($this->getFixtureSrcDir(), '1.txt');
        $this->gitCommitAll($this->getFixtureSrcDir(), 'Commit number 1');

        $this->gitCreateFixtureFile($this->getFixtureSrcDir(), '2.txt');
        $this->gitCommitAll($this->getFixtureSrcDir(), 'Commit number 2');

        $remoteBranch = 'testbranch';

        $output = $this->runRoboCommand(sprintf('artefact --src=%s --push --force-push %s --branch=testbranch', $this->getFixtureSrcDir(), $this->getFixtureRemoteDir()));

        $output = implode(PHP_EOL, $output);
        $this->assertContains('Will push:             Yes', $output);
        $this->assertContains('Will force-push:       Yes', $output);

        $this->assertContains(sprintf('Pushed branch "%s" with commit message "Deployment commit"', $remoteBranch), $output);

        $remoteCommits = $this->gitGetAllCommits($this->getFixtureRemoteDir());
        $this->assertEquals([
            'Commit number 1',
            'Commit number 2',
            'Deployment commit',
        ], $remoteCommits);

        $this->gitAssertFilesExist($this->getFixtureRemoteDir(), [
            '1.txt',
            '2.txt',
        ], $remoteBranch);
    }
}
