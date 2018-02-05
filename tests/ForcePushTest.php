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

        $this->assertBuildFailure('--branch=*invalid');

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

    public function testGitignoreCustom()
    {
        $this->gitCreateFixtureFile($this->src, 'mygitignore', '3.txt');
        $this->gitCreateFixtureCommits(2);
        $this->gitCreateFixtureFile($this->src, '3.txt');

        $this->assertBuildSuccess('--gitignore='.$this->src.DIRECTORY_SEPARATOR.'mygitignore');

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, '3.txt');

        // Now, remove the .gitignore and push again.
        $this->gitRemoveFixtureFile($this->src, 'mygitignore');
        $this->gitCommitAll($this->src, 'Commit number 3');
        $this->assertBuildSuccess();
        $this->assertFixtureCommits(3, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testGitignoreCustomRemoveCommittedFiles()
    {
        $this->gitCreateFixtureFile($this->src, '.gitignore', ['3.txt']);
        $this->gitCreateFixtureFile($this->src, '3.txt');
        $this->gitCreateFixtureFile($this->src, 'subdir/4.txt');
        $this->gitCreateFixtureFile($this->src, 'subdir/5.txt');
        $this->gitCreateFixtureCommits(2);
        $this->gitCommitAll($this->src, 'Custom third commit');

        $this->gitAssertFilesCommitted($this->src, ['1.txt', '2.txt', 'subdir/4.txt', 'subdir/5.txt']);
        $this->gitAssertNoFilesCommitted($this->src, ['3.txt']);

        $this->gitCreateFixtureFile($this->src, 'mygitignore', ['1.txt', '3.txt', '5.txt']);

        $this->assertBuildSuccess('--gitignore='.$this->src.DIRECTORY_SEPARATOR.'mygitignore');

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Custom third commit', 'Deployment commit'], false);
        $this->gitAssertFilesCommitted($this->dst, ['2.txt', 'subdir/4.txt'], 'testbranch');
        $this->gitAssertNoFilesCommitted($this->dst, ['1.txt', '3.txt', 'subdir/5.txt'], 'testbranch');
        $this->gitAssertFilesExist($this->dst, ['2.txt', 'subdir/4.txt'], 'testbranch');
        $this->gitAssertFilesNotExist($this->dst, ['1.txt', '3.txt', 'subdir/5.txt'], 'testbranch');
    }

    public function testBuildTag()
    {
        $this->gitCreateFixtureCommits(2);
        $this->gitAddTag($this->src, 'tag1');

        $this->assertBuildSuccess('--branch=[tags]', 'tag1');

        $this->assertFixtureCommits(2, $this->dst, 'tag1', ['Deployment commit']);
    }

    public function testBuildMultipleTags()
    {
        $this->gitCreateFixtureCommits(2);
        $this->gitAddTag($this->src, 'tag1');
        $this->gitAddTag($this->src, 'tag2');

        $this->assertBuildSuccess('--branch=[tags]', 'tag1-tag2');

        $this->assertFixtureCommits(2, $this->dst, 'tag1-tag2', ['Deployment commit']);
    }

    public function testBuildMultipleTagsDelimiter()
    {
        $this->gitCreateFixtureCommits(2);
        $this->gitAddTag($this->src, 'tag1');
        $this->gitAddTag($this->src, 'tag2');

        $this->assertBuildSuccess('--branch=[tags:__]', 'tag1__tag2');

        $this->assertFixtureCommits(2, $this->dst, 'tag1__tag2', ['Deployment commit']);
    }
}
