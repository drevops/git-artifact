<?php

namespace IntegratedExperts\Robo\Tests;

/**
 * Class ForcePushTest.
 */
class ForcePushTest extends AbstractTest
{

    protected function setUp()
    {
        $this->mode = 'force-push';
        parent::setUp();
    }

    public function testBuild()
    {
        $this->gitCreateFixtureCommits(2);

        $output = $this->assertBuildSuccess();
        $this->assertContains('Mode:                  force-push', $output);
        $this->assertContains('Will push:             Yes', $output);

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testBuildMoreCommits()
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

        $this->gitCreateFixtureCommits(3, 2);
        $this->assertBuildSuccess();

        $this->assertFixtureCommits(5, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testIdempotence()
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

        $this->assertBuildSuccess();
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testCleanupAfterSuccess()
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

        $this->assertGitCurrentBranch($this->src, $this->currentBranch);
        $this->assertGitNoRemote($this->src, $this->remote);
    }

    public function testCleanupAfterFailure()
    {
        $this->gitCreateFixtureCommits(1);

        $this->assertBuildFailure('--branch=&invalid');

        $this->assertGitCurrentBranch($this->src, $this->currentBranch);
        $this->assertGitNoRemote($this->src, $this->remote);
    }

    public function testGitignore()
    {
        $this->gitCreateFixtureFile($this->src, '.gitignore', '3.txt');
        $this->gitCreateFixtureCommits(2);
        $this->gitCreateFixtureFile($this->src, '3.txt');

        $this->assertBuildSuccess();

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, '3.txt');

        // Now, remove the .gitignore and push again.
        $this->gitRemoveFixtureFile($this->src, '.gitignore');
        $this->gitCommitAll($this->src, 'Commit number 3');
        $this->assertBuildSuccess();
        $this->assertFixtureCommits(3, $this->dst, 'testbranch', ['Deployment commit']);
    }
}
