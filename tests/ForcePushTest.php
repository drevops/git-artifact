<?php

namespace IntegratedExperts\Robo\Tests;

use IntegratedExperts\Robo\ArtefactTrait;

/**
 * Class ForcePushTest.
 */
class ForcePushTest extends AbstractTest
{

    public function testForcePush()
    {
        $remoteBranch = 'testbranch';

        $this->gitCreateFixtureFile($this->getFixtureSrcDir(), '1.txt');
        $this->gitCommitAll($this->getFixtureSrcDir(), 'Commit number 1');

        $this->gitCreateFixtureFile($this->getFixtureSrcDir(), '2.txt');
        $this->gitCommitAll($this->getFixtureSrcDir(), 'Commit number 2');

        $output = $this->runBuild(sprintf('--push --mode=force-push --branch=%s', $remoteBranch));
        $this->assertContains(sprintf('Mode:                  %s', ArtefactTrait::modeForcePush()), $output);
        $this->assertContains(sprintf('Will push:             Yes'), $output);

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
