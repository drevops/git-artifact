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

        $this->assertFixtureCommits(2, $this->fixtureRemoteRepo, 'testbranch', ['Deployment commit']);
    }

    public function testBuildMoreCommits()
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();

        $this->assertFixtureCommits(2, $this->fixtureRemoteRepo, 'testbranch', ['Deployment commit']);

        $this->gitCreateFixtureCommits(3, 2);
        $this->assertBuildSuccess();

        $this->assertFixtureCommits(5, $this->fixtureRemoteRepo, 'testbranch', ['Deployment commit']);
    }

    public function testCleanup()
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();
        $this->assertFixtureCommits(2, $this->fixtureRemoteRepo, 'testbranch', ['Deployment commit']);

        $this->assertGitCurrentBranch($this->fixtureSrcRepo, $this->currentBranch);
        $this->assertGitNoRemote($this->fixtureSrcRepo, $this->remote);
    }

    public function testIdempotence()
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();
        $this->assertFixtureCommits(2, $this->fixtureRemoteRepo, 'testbranch', ['Deployment commit']);

        $this->assertGitCurrentBranch($this->fixtureSrcRepo, $this->currentBranch);
        $this->assertGitNoRemote($this->fixtureSrcRepo, $this->remote);

        $this->assertBuildSuccess();
        $this->assertFixtureCommits(2, $this->fixtureRemoteRepo, 'testbranch', ['Deployment commit']);

        $this->assertGitCurrentBranch($this->fixtureSrcRepo, $this->currentBranch);
        $this->assertGitNoRemote($this->fixtureSrcRepo, $this->remote);
    }
}
