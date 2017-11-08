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
        parent::setUp();
        $this->currentBranch = 'master-'.date('Y-m-d_H-i-s', $this->now);
    }

    public function testPushNoChanges()
    {
        $this->gitCreateFixtureCommits(1);
        $output = $this->runRoboCommand(sprintf('artefact --src=%s --push %s', $this->src, $this->dst));
        $output = implode(PHP_EOL, $output);

        $this->assertContains(sprintf('Remote branch:         %s', $this->currentBranch), $output);
        $this->assertContains('Will push:             Yes', $output);

        $this->assertContains(sprintf('Pushed branch "%s" with commit message "Deployment commit"', $this->currentBranch), $output);
        $this->assertNotContains('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);

        $remoteCommits = $this->gitGetAllCommits($this->dst);
        $this->assertEquals([
            'Commit number 1',
            'Deployment commit',
        ], $remoteCommits);

        $this->gitAssertFilesExist($this->dst, '1.txt', $this->currentBranch);
    }

    public function testPushUncommittedChanges()
    {
        $this->gitCreateFixtureCommits(1);
        $this->gitCreateFixtureFile($this->src, '2.txt');
        $output = $this->runRoboCommand(sprintf('artefact --src=%s --push %s', $this->src, $this->dst));
        $output = implode(PHP_EOL, $output);

        $this->assertContains('Will push:             Yes', $output);

        $this->assertContains(sprintf('Pushed branch "%s" with commit message "Deployment commit"', $this->currentBranch), $output);
        $this->assertNotContains('Cowardly refusing to push to remote. Use --push option to perform an actual push.', $output);

        $remoteCommits = $this->gitGetAllCommits($this->dst);
        $this->assertEquals([
            'Commit number 1',
            'Deployment commit',
        ], $remoteCommits);

        $this->gitAssertFilesExist($this->dst, [
            '1.txt',
            '2.txt',
        ], $this->currentBranch);
    }

    public function testPushGitignore()
    {
        $this->gitCreateFixtureFile($this->src, '1.txt');
        $this->gitCommitAll($this->src, 'Commit number 1');
        $this->gitCreateFixtureFile($this->src, '.gitignore', '2.txt');
        $this->gitCommitAll($this->src, 'Commit number 2. Added gitignore file');
        $this->gitCreateFixtureFile($this->src, '2.txt');
        $this->gitCreateFixtureFile($this->src, '3.txt');
        $this->gitCommitAll($this->src, 'Commit number 3');

        $output = $this->runRoboCommand(sprintf('artefact --src=%s --push %s', $this->src, $this->dst));
        $output = implode(PHP_EOL, $output);
        $this->assertContains('Will push:             Yes', $output);
        $this->assertContains(sprintf('Pushed branch "%s" with commit message "Deployment commit"', $this->currentBranch), $output);

        $remoteCommits = $this->gitGetAllCommits($this->dst);
        // @note: Deployment commit has not been added since there were no
        // modified files.
        $this->assertEquals([
            'Commit number 1',
            'Commit number 2. Added gitignore file',
            'Commit number 3',
            'Deployment commit',
        ], $remoteCommits);

        $this->gitAssertFilesExist($this->dst, [
            '1.txt',
            '3.txt',
            '.gitignore',
        ], $this->currentBranch);
        $this->gitAssertFilesNotExist($this->dst, '2.txt', $this->currentBranch);
    }

    public function testPushGitignoreReplaced()
    {
        $gitignoreArtefactFile = '.gitignore.artefact';

        $this->gitCreateFixtureFile($this->src, '1.txt');
        $this->gitCommitAll($this->src, 'Commit number 1');
        $this->gitCreateFixtureFile($this->src, '.gitignore', '2.txt');
        $this->gitCommitAll($this->src, 'Commit number 2. Added gitignore file');
        $this->gitCreateFixtureFile($this->src, '2.txt');
        $this->gitCreateFixtureFile($this->src, '3.txt');
        $this->gitCommitAll($this->src, 'Commit number 3');
        $this->gitCreateFixtureFile($this->src, $gitignoreArtefactFile, '4.txt');
        $this->gitCreateFixtureFile($this->src, '4.txt');

        $output = $this->runRoboCommand(sprintf('artefact --src=%s --push %s --gitignore=%s', $this->src, $this->dst, $this->src.DIRECTORY_SEPARATOR.$gitignoreArtefactFile));
        $output = implode(PHP_EOL, $output);
        $this->assertContains(sprintf('Gitignore file:        %s', $this->src.DIRECTORY_SEPARATOR.$gitignoreArtefactFile), $output);
        $this->assertContains('Will push:             Yes', $output);
        $this->assertContains(sprintf('Pushed branch "%s" with commit message "Deployment commit"', $this->currentBranch), $output);

        $remoteCommits = $this->gitGetAllCommits($this->dst);
        // @note: Deployment commit has not been added since there were no
        // modified files.
        $this->assertEquals([
            'Commit number 1',
            'Commit number 2. Added gitignore file',
            'Commit number 3',
            'Deployment commit',
        ], $remoteCommits);

        $this->gitAssertFilesExist($this->dst, [
            '1.txt',
            '2.txt',
            '3.txt',
            '.gitignore',
        ], $this->currentBranch);
        $this->gitAssertFilesNotExist($this->dst, '4.txt', $this->currentBranch);
    }

    public function testPushsSingleTags()
    {
        $this->gitCreateFixtureFile($this->src, '1.txt');
        $this->gitCommitAll($this->src, 'Commit number 1');
        $this->gitAddTag($this->src, 'tag1');
        $remoteBranch = 'tag1';

        $output = $this->runRoboCommand(sprintf('artefact --src=%s --push %s --branch=[tags]', $this->src, $this->dst));
        $output = implode(PHP_EOL, $output);

        $this->assertContains('Will push:             Yes', $output);
        $this->assertContains(sprintf('Pushed branch "%s" with commit message "Deployment commit"', $remoteBranch), $output);

        $remoteCommits = $this->gitGetAllCommits($this->dst);
        // @note: Deployment commit has not been added since there were no
        // modified files.
        $this->assertEquals([
            'Commit number 1',
            'Deployment commit',
        ], $remoteCommits);

        $this->gitAssertFilesExist($this->dst, [
            '1.txt',
        ], $remoteBranch);
    }

    public function testPushMultipleTags()
    {
        $this->gitCreateFixtureFile($this->src, '1.txt');
        $this->gitCommitAll($this->src, 'Commit number 1');
        $this->gitAddTag($this->src, 'tag1');
        $this->gitAddTag($this->src, 'tag2');
        $remoteBranch = 'tag1-tag2';

        $output = $this->runRoboCommand(sprintf('artefact --src=%s --push %s --branch=[tags:-]', $this->src, $this->dst));
        $output = implode(PHP_EOL, $output);

        $this->assertContains('Will push:             Yes', $output);
        $this->assertContains(sprintf('Pushed branch "%s" with commit message "Deployment commit"', $remoteBranch), $output);

        $remoteCommits = $this->gitGetAllCommits($this->dst);
        // @note: Deployment commit has not been added since there were no
        // modified files.
        $this->assertEquals([
            'Commit number 1',
            'Deployment commit',
        ], $remoteCommits);

        $this->gitAssertFilesExist($this->dst, [
            '1.txt',
        ], $remoteBranch);
    }

    public function testReport()
    {
        $report = $this->src.DIRECTORY_SEPARATOR.'report.txt';

        $this->gitCreateFixtureCommits(1);
        $this->runRoboCommand(sprintf('artefact --src=%s --push %s --report=%s', $this->src, $this->dst, $report));

        $this->assertFileExists($report);
        $output = file_get_contents($report);

        $this->assertContains('Artefact report', $output);
        $this->assertContains(sprintf('Source repository: %s', $this->src), $output);
        $this->assertContains(sprintf('Remote repository: %s', $this->dst), $output);
        $this->assertContains(sprintf('Remote branch:     %s', $this->currentBranch), $output);
        $this->assertContains('Gitignore file:    No', $output);
        $this->assertContains('Push result:       Success', $output);
    }
}
