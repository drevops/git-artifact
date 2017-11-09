<?php

namespace IntegratedExperts\Robo\Tests;

/**
 * Class BranchTest.
 */
class BranchTest extends AbstractTest
{

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->mode = 'branch';
        parent::setUp();
    }

    public function testBuild()
    {
        $this->gitCreateFixtureCommits(2);

        $output = $this->assertBuildSuccess();
        $this->assertContains('WARNING! Provided branch name does not have a token', $output);
        $this->assertContains('Mode:                  branch', $output);
        $this->assertContains('Will push:             Yes', $output);

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testBuildMoreCommitsSameBranch()
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

        $this->gitCreateFixtureCommits(3, 2);
        $this->assertBuildFailure();

        // Make sure that broken artefact was not pushed.
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testBuildMoreCommits()
    {
        $this->gitCreateFixtureCommits(2);

        $this->now = time() - rand(1, 10 * 60);
        $branch1 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $output = $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s]', $branch1);
        $this->assertContains('Remote branch:         '.$branch1, $output);
        $this->assertNotContains('WARNING! Provided branch name does not have a token', $output);

        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);

        $this->gitCreateFixtureCommits(3, 2);

        $this->now = time() - rand(1, 10 * 60);
        $branch2 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $output = $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s]', $branch2);
        $this->assertContains('Remote branch:         '.$branch2, $output);
        $this->assertFixtureCommits(5, $this->dst, $branch2, ['Deployment commit']);

        // Also, check that no changes were done to branch1.
        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
    }
}
