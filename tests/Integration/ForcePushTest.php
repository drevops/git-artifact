<?php

namespace IntegratedExperts\Robo\Tests\Integration;

/**
 * Class ForcePushTest.
 *
 * @group integration
 */
class ForcePushTest extends AbstractIntegrationTest
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

    public function testSubRepos()
    {
        $this->gitCreateFixtureCommits(2);

        $this->gitCreateFixtureFile($this->src, 'c');

        $this->gitInitRepo($this->src.DIRECTORY_SEPARATOR.'r1/');
        $this->gitCreateFixtureFile($this->src, 'r1/c');

        $this->gitInitRepo($this->src.DIRECTORY_SEPARATOR.'r1/');
        $this->gitCreateFixtureFile($this->src, 'r1/c');

        $this->gitInitRepo($this->src.DIRECTORY_SEPARATOR.'r2/r21');
        $this->gitCreateFixtureFile($this->src, 'r2/r21/c');

        $this->gitInitRepo($this->src.DIRECTORY_SEPARATOR.'r3/r31/r311');
        $this->gitCreateFixtureFile($this->src, 'r3/r31/r311/c');

        $this->gitCommitAll($this->src, 'Commit number 3');

        $this->gitAssertFilesExist($this->src, ['r1/c']);
        $this->gitAssertFilesNotExist($this->src, ['r1/.git/index']);
        $this->gitAssertFilesNotExist($this->src, ['r2/r21.git/index']);
        $this->gitAssertFilesNotExist($this->src, ['r3/r31/r311/.git/index']);

        $output = $this->assertBuildSuccess('--debug');
        $this->assertContains(sprintf('Removing sub-repository "%s"', $this->src.DIRECTORY_SEPARATOR.'r1/.git'), $output);
        $this->assertContains(sprintf('Removing sub-repository "%s"', $this->src.DIRECTORY_SEPARATOR.'r2/r21/.git'), $output);
        $this->assertContains(sprintf('Removing sub-repository "%s"', $this->src.DIRECTORY_SEPARATOR.'r3/r31/r311/.git'), $output);
        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Commit number 3', 'Deployment commit']);

        $this->gitAssertFilesExist($this->dst, ['r1/c']);
        $this->gitAssertFilesExist($this->dst, ['r2/r21/c']);
        $this->gitAssertFilesExist($this->dst, ['r3/r31/r311/c']);
        $this->gitAssertFilesNotExist($this->dst, ['r1/.git/index']);
        $this->gitAssertFilesNotExist($this->dst, ['r1/.git']);
        $this->gitAssertFilesNotExist($this->dst, ['r2/r21/.git/index']);
        $this->gitAssertFilesNotExist($this->dst, ['r2/r21/.git']);
        $this->gitAssertFilesNotExist($this->dst, ['r3/r31/311/.git/index']);
        $this->gitAssertFilesNotExist($this->dst, ['r3/r31/311/.git']);
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

        $output = $this->assertBuildFailure('--branch=*invalid');

        $this->assertContains('Incorrect value "*invalid" specified for git remote branch', $output);
        $this->assertGitCurrentBranch($this->src, $this->currentBranch);
        $this->assertGitNoRemote($this->src, $this->remote);
    }

    public function testGitignore()
    {
        $this->gitCreateFixtureFile($this->src, '.gitignore', 'f3');
        $this->gitCreateFixtureCommits(2);
        $this->gitCreateFixtureFile($this->src, 'f3');

        $this->assertBuildSuccess();

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, 'f3');

        // Now, remove the .gitignore and push again.
        $this->gitRemoveFixtureFile($this->src, '.gitignore');
        $this->gitCommitAll($this->src, 'Commit number 3');
        $this->assertBuildSuccess();
        $this->assertFixtureCommits(3, $this->dst, 'testbranch', ['Deployment commit']);
    }

    public function testGitignoreCustom()
    {
        $this->gitCreateFixtureCommits(2);
        $this->gitCreateFixtureFile($this->src, 'uic');
        $this->gitCreateFixtureFile($this->src, 'uc');

        $this->gitCreateFixtureFile($this->src, 'mygitignore', 'uic');

        $this->assertBuildSuccess('--gitignore='.$this->src.DIRECTORY_SEPARATOR.'mygitignore');

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
        $this->gitAssertFilesNotExist($this->dst, 'uic');
        $this->gitAssertFilesExist($this->dst, 'uc');

        // Now, remove the .gitignore and push again.
        // We have to create 'uic' file since it was rightfully
        // removed during previous build run and the source repo branch was not
        // reset (uncommitted files would be removed, unless they are excluded
        // in .gitignore).
        $this->gitCreateFixtureFile($this->src, 'uic');
        $this->gitRemoveFixtureFile($this->src, 'mygitignore');
        $this->gitCommitAll($this->src, 'Commit number 3');
        $this->assertBuildSuccess();

        $this->assertFixtureCommits(3, $this->dst, 'testbranch', ['Deployment commit'], false);
        $this->gitAssertFilesCommitted($this->dst, ['f1', 'f2', 'uic'], 'testbranch');
        $this->gitAssertFilesExist($this->dst, ['f1', 'f2', 'uic'], 'testbranch');
        $this->gitAssertNoFilesCommitted($this->dst, ['uc'], 'testbranch');
    }

    public function testGitignoreCustomRemoveCommittedFiles()
    {
        $this->gitCreateFixtureFile($this->src, '.gitignore', ['ii', 'ic']);

        $this->gitCreateFixtureFile($this->src, 'ii');
        $this->gitCreateFixtureFile($this->src, 'ic');
        $this->gitCreateFixtureFile($this->src, 'd/cc');
        $this->gitCreateFixtureFile($this->src, 'd/ci');
        $this->gitCreateFixtureCommits(2);
        $this->gitCommitAll($this->src, 'Custom third commit');
        $this->gitCreateFixtureFile($this->src, 'ui');
        $this->gitCreateFixtureFile($this->src, 'uc');
        $this->gitAssertFilesCommitted($this->src, ['.gitignore', 'f1', 'f2', 'd/cc', 'd/ci']);
        $this->gitAssertNoFilesCommitted($this->src, ['ii', 'ic', 'ui', 'uc']);

        $this->gitCreateFixtureFile($this->src, 'mygitignore', ['f1', 'ii', 'ci', 'ui']);

        $this->assertBuildSuccess('--gitignore='.$this->src.DIRECTORY_SEPARATOR.'mygitignore');

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Custom third commit', 'Deployment commit'], false);
        $this->gitAssertFilesCommitted($this->dst, ['.gitignore', 'f2', 'ic', 'd/cc', 'uc'], 'testbranch');
        $this->gitAssertNoFilesCommitted($this->dst, ['f1', 'ii', 'd/ci', 'ui'], 'testbranch');
        $this->gitAssertFilesExist($this->dst, ['f2', 'ic', 'd/cc', 'uc'], 'testbranch');
        $this->gitAssertFilesNotExist($this->dst, ['f1', 'ii', 'd/ci', 'ui'], 'testbranch');
    }

    public function testGitignoreCustomWhitelisting()
    {
        $this->gitCreateFixtureFile($this->src, '.gitignore', ['ii', 'ic', 'd_ic', 'd_ii', '/vendor']);

        $this->gitCreateFixtureFile($this->src, 'ii');
        $this->gitCreateFixtureFile($this->src, 'ic');
        $this->gitCreateFixtureFile($this->src, 'cc');
        $this->gitCreateFixtureFile($this->src, 'ci');

        $this->gitCreateFixtureFile($this->src, 'd_cc/sub_cc');
        $this->gitCreateFixtureFile($this->src, 'd_ci/sub_ci');
        $this->gitCreateFixtureFile($this->src, 'd_ic/sub_ic');
        $this->gitCreateFixtureFile($this->src, 'd_ii/sub_ii');

        $this->gitCreateFixtureFile($this->src, 'vendor/ve_ii');
        $this->gitCreateFixtureFile($this->src, 'vendor_cc');
        $this->gitCreateFixtureFile($this->src, 'vendor_com with space com.txt');
        $this->gitCreateFixtureFile($this->src, 'dir_other/vendor/ve_cc');

        $this->gitCreateFixtureCommits(2);

        $this->gitCommitAll($this->src, 'Custom third commit');

        $this->gitAssertFilesCommitted($this->src, [
            '.gitignore', 'f1', 'f2',
            'cc', 'ci',
            'd_cc/sub_cc', 'd_ci/sub_ci',
            'vendor_cc', 'dir_other/vendor/ve_cc', 'vendor_com with space com.txt',
        ]);

        $this->gitAssertNoFilesCommitted($this->src, [
            'ii', 'ic', 'ui', 'uc', 'ud',
            'd_ic/sub_ic', 'd_ii/sub_ii',
            'vendor/ve_ii',
        ]);

        $this->gitCreateFixtureFile($this->src, 'ui');
        $this->gitCreateFixtureFile($this->src, 'uc');
        $this->gitCreateFixtureFile($this->src, 'ud');
        $this->gitCreateFixtureFile($this->src, 'd_ui/sub_ui');
        $this->gitCreateFixtureFile($this->src, 'd_uc/sub_uc');
        $this->gitCreateFixtureFile($this->src, 'd_ud/sub_ud');

        // Now, create a custom .gitignore and add non-ignored files
        // (whitelisting).
        $this->gitCreateFixtureFile($this->src, 'mygitignore', [
            '/*', '!f2', '!ic', '!cc', '!uc',
            '!d_cc', '!d_ic', '!d_uc',
            '!vendor',
        ]);

        // Run the build.
        $this->assertBuildSuccess('--debug --gitignore='.$this->src.DIRECTORY_SEPARATOR.'mygitignore');

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Custom third commit', 'Deployment commit'], false);

        $this->gitAssertFilesCommitted($this->dst, [
            'f2', 'ic', 'cc', 'uc',
            'd_cc/sub_cc', 'd_ic/sub_ic', 'd_uc/sub_uc',
            'vendor/ve_ii',
        ], 'testbranch');

        $this->gitAssertNoFilesCommitted($this->dst, [
            'f1', 'ii', 'ci', 'ui', 'ud',
            'd_ci/sub_ci', 'd_ii/sub_ii', 'd_ui/sub_ui', 'd_ud/sub_ud',
            'vendor_cc', 'dir_other/vendor/ve_cc', 'vendor_com with space com.txt',
        ], 'testbranch');

        $this->gitAssertFilesExist($this->dst, [
            'f2', 'ic', 'cc', 'uc',
            'd_cc/sub_cc', 'd_ic/sub_ic', 'd_uc/sub_uc',
            'vendor/ve_ii',
        ], 'testbranch');
        $this->gitAssertFilesNotExist($this->dst, [
            'f1', 'ii', 'ci', 'ui', 'ud',
            'd_ci/sub_ci',
            'd_ii/sub_ii', 'd_ui/sub_ui', 'd_ud/sub_ud',
            'vendor_cc', 'dir_other/vendor/ve_cc', 'vendor_com with space com.txt',
        ], 'testbranch');
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
