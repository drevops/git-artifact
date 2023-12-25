<?php

declare(strict_types = 1);

namespace DrevOps\Robo\Tests\Integration;

/**
 * Class BranchTest.
 *
 * @group integration
 *
 * @covers \DrevOps\Robo\GitTrait
 * @covers \DrevOps\Robo\ArtefactTrait
 * @covers \DrevOps\Robo\FilesystemTrait
 */
class BranchTest extends AbstractIntegrationTestCase
{

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        $this->mode = 'branch';
        parent::setUp();
    }

    public function testBuild(): void
    {
        $this->gitCreateFixtureCommits(2);

        $output = $this->assertBuildSuccess();
        $this->assertStringContainsString('WARNING! Provided branch name does not have a token', $output);
        $this->assertStringContainsString('Mode:                  branch', $output);
        $this->assertStringContainsString('Will push:             Yes', $output);

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testBuildMoreCommitsSameBranch(): void
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

        $this->gitCreateFixtureCommits(3, 2);
        $this->assertBuildFailure();

        // Make sure that broken artifact was not pushed.
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testBuildMoreCommits(): void
    {
        $this->gitCreateFixtureCommits(2);

        $this->now = time() - rand(1, 10 * 60);
        $branch1 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $output = $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s]', $branch1);
        $this->assertStringContainsString('Remote branch:         '.$branch1, $output);
        $this->assertStringNotContainsString('WARNING! Provided branch name does not have a token', $output);

        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);

        $this->gitCreateFixtureCommits(3, 2);

        $this->now = time() - rand(1, 10 * 60);
        $branch2 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $output = $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s]', $branch2);
        $this->assertStringContainsString('Remote branch:         '.$branch2, $output);
        $this->assertFixtureCommits(5, $this->dst, $branch2, ['Deployment commit']);

        // Also, check that no changes were done to branch1.
        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
    }

    public function testCleanupAfterSuccess(): void
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess();
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

        $this->assertGitCurrentBranch($this->src, $this->currentBranch);
        $this->assertGitNoRemote($this->src, $this->remote);
    }

    public function testCleanupAfterFailure(): void
    {
        $this->gitCreateFixtureCommits(2);

        $this->assertBuildSuccess('', 'testbranch');
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);

        $this->gitCreateFixtureCommits(3, 2);
        // Trigger erroneous build by pushing to the same branch.
        $this->assertBuildFailure('', 'testbranch');

        $this->assertGitCurrentBranch($this->src, $this->currentBranch);
        $this->assertGitNoRemote($this->src, $this->remote);
    }

    public function testGitignore(): void
    {
        $this->gitCreateFixtureFile($this->src, '.gitignore', 'f3');
        $this->gitCreateFixtureCommits(2);
        $this->gitCreateFixtureFile($this->src, 'f3');

        $this->now = time() - rand(1, 10 * 60);
        $branch1 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s]', $branch1);

        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, 'f3');

        // Now, remove the .gitignore and push again.
        $this->gitRemoveFixtureFile($this->src, '.gitignore');
        $this->gitCommitAll($this->src, 'Commit number 3');
        $this->now = time() - rand(1, 10 * 60);
        $branch2 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s]', $branch2);

        $this->assertFixtureCommits(3, $this->dst, $branch2, ['Deployment commit']);

        // Assert that branch from previous deployment was not affected.
        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, 'f3');
    }

    public function testGitignoreCustom(): void
    {
        $this->gitCreateFixtureFile($this->src, 'mygitignore', 'f3');
        $this->gitCreateFixtureCommits(2);
        $this->gitCreateFixtureFile($this->src, 'f3');

        $this->now = time() - rand(1, 10 * 60);
        $branch1 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s] --gitignore='.$this->src.DIRECTORY_SEPARATOR.'mygitignore', $branch1);

        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, 'f3');

        // Now, remove the .gitignore and push again.
        $this->gitCreateFixtureFile($this->src, 'f3');
        $this->gitRemoveFixtureFile($this->src, 'mygitignore');
        $this->gitCommitAll($this->src, 'Commit number 3');
        $this->now = time() - rand(1, 10 * 60);
        $branch2 = 'testbranch-'.date('Y-m-d_H-i-s', $this->now);
        $this->assertBuildSuccess('--branch=testbranch-[timestamp:Y-m-d_H-i-s]', $branch2);

        $this->assertFixtureCommits(3, $this->dst, $branch2, ['Deployment commit']);

        // Assert that branch from previous deployment was not affected.
        $this->assertFixtureCommits(2, $this->dst, $branch1, ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, 'f3');
    }
}
