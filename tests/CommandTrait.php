<?php

namespace IntegratedExperts\Robo\Tests;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Error\Error;
use SebastianBergmann\GlobalState\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Trait CommandTrait
 *
 * @package IntegratedExperts\Robo\Tests
 */
trait CommandTrait
{

    /**
     * Fixture source repository directory.
     *
     * @var string
     */
    protected $fixtureSrcRepo;

    /**
     * Fixture remote repository directory.
     *
     * @var string
     */
    protected $fixtureRemoteRepo;

    /**
     * File system.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * Flag to denote that debug information should be printed.
     *
     * @var bool
     */
    protected $printDebug;

    /**
     * Setup test.
     *
     * To be called by test's setUp() method.
     *
     * @param string $dir
     *   Fixture repository directory.
     * @param bool   $printDebug
     *   Optional flag to print debug information when running commands.
     *   Defaults to FALSE.
     */
    protected function setUp($src, $remote, $printDebug = false)
    {
        $this->printDebug = $printDebug;
        $this->fs = new Filesystem();
        $this->fixtureSrcRepo = $src;
        $this->gitInitRepo($this->fixtureSrcRepo);
        $this->fixtureRemoteRepo = $remote;
        $this->gitInitRepo($this->fixtureRemoteRepo);
        // Allow pushing into already checked out branch. We need this to
        // avoid additional management of fixture repository.
        $this->runGitCommand('config receive.denyCurrentBranch ignore', $this->fixtureRemoteRepo);
    }

    /**
     * Tear down test.
     *
     * To be called by test's tearDown() method.
     */
    protected function tearDown()
    {
        if ($this->fs->exists($this->fixtureSrcRepo)) {
            $this->fs->remove($this->fixtureSrcRepo);
        }
        if ($this->fs->exists($this->fixtureRemoteRepo)) {
            $this->fs->remove($this->fixtureRemoteRepo);
        }
    }

    /**
     * Init git repository.
     *
     * @param string $path
     *   Path to the repository directory.
     */
    protected function gitInitRepo($path)
    {
        if ($this->fs->exists($path)) {
            $this->fs->remove($path);
        }
        $this->fs->mkdir($path);

        $this->runGitCommand('init', $path);
    }

    /**
     * Get all commit hashes in the repository.
     *
     * @param string $path
     *   Optional path to the repository directory. If not provided, fixture
     *   directory is used.
     *
     * @return array
     *   Array of commit hashes, sorted from the earliest to the latest commit.
     */
    protected function gitGetAllCommits($path = null, $format = '%s')
    {
        $commits = [];
        try {
            $commits = $this->runGitCommand('log --all --format="'.$format.'"', $path);
        } catch (\Exception $exception) {
            $output = trim($exception->getPrevious()->getMessage());
            // Different versions of Git may produce these expected messages.
            $expectedErrorMessages = [
                "fatal: bad default revision 'HEAD'",
                "fatal: your current branch 'master' does not have any commits yet",
            ];
            if (!in_array($output, $expectedErrorMessages)) {
                throw $exception;
            }
        }

        return array_reverse(array_filter($commits));
    }

    /**
     * Get a range of commits.
     *
     * @param array  $range
     *   Array of commit indexes, stating from 1.
     * @param string $path
     *   Optional path to the repository directory. If not provided, fixture
     *   directory is used.
     *
     * @return array
     *   Array of commit hashes, ordered by keys in the $range.
     */
    protected function gitGetCommitsHashesFromRange(array $range, $path = null)
    {
        $commits = $this->gitGetAllCommits($path);

        array_walk($range, function (&$v) {
            $v -= 1;
        });

        $ret = [];
        foreach ($range as $key) {
            $ret[] = $commits[$key];
        }

        return $ret;
    }

    /**
     * Create multiple fixture commits.
     *
     * @param int    $count
     *   Number of commits to create.
     * @param string $path
     *   Optional path to the repository directory. If not provided, fixture
     *   directory is used.
     */
    protected function gitCreateFixtureCommits($count, $offset = 0, $path = null)
    {
        $path = $path ? $path : $this->fixtureSrcRepo;
        for ($i = $offset; $i < $count + $offset; $i++) {
            $this->gitCreateFixtureCommit($i + 1, $path);
        }
    }

    /**
     * Create fixture commit with specified index.
     *
     * @param string $index
     *   Index of the commit to be used in the message.
     * @param string $path
     *   Optional path to the repository directory. If not provided, fixture
     *   directory is used.
     *
     * @return string
     *   Hash of created commit.
     */
    protected function gitCreateFixtureCommit($index, $path = null)
    {
        $path = $path ? $path : $this->fixtureSrcRepo;
        $this->gitCreateFixtureFile($path, $index.'.txt');
        $this->runGitCommand(sprintf('add %s.txt', $index), $path);
        $this->runGitCommand(sprintf('commit -am "Commit number %s"', $index), $path);

        $output = $this->runGitCommand('rev-parse HEAD', $path);

        return trim(implode(' ', $output));
    }

    /**
     * Commit all uncommitted files.
     *
     * @param string $path
     *   Path to repository.
     * @param string $message
     *   Commit message.
     */
    protected function gitCommitAll($path, $message)
    {
        $this->runGitCommand('add .', $path);
        $this->runGitCommand(sprintf('commit -am "%s"', $message), $path);
    }

    /**
     * Checkout branch.
     *
     * @param string $path
     *   Path to repository.
     * @param string $branch
     *   Branch name.
     */
    protected function gitCheckout($path, $branch)
    {
        try {
            $this->runGitCommand(sprintf('checkout %s', $branch), $path);
        } catch (Error $error) {
            $allowedFails = [
                "error: pathspec '$branch' did not match any file(s) known to git.",
            ];

            $output = explode(PHP_EOL, $error->getPrevious()->getMessage());
            // Re-throw exception in not one of the allowed ones.
            if (empty(array_intersect($output, $allowedFails))) {
                throw $error;
            }
        }
    }

    protected function gitReset($path)
    {
        $this->runGitCommand('reset --hard', $path);
        $this->runGitCommand('clean -dfx', $path);
    }

    /**
     * Create fixture file at provided path.
     *
     * @param string  $path
     *    File path.
     * @param  string $name
     *    Optional file name.
     *
     * @return string
     *   Created file name.
     */
    protected function gitCreateFixtureFile($path, $name = null, $content = null)
    {
        $name = $name ? $name : 'tmp'.rand(1000, 100000).'.txt';
        $path = $path.DIRECTORY_SEPARATOR.$name;
        $this->fs->touch($path);
        if (!empty($content)) {
            $content = is_array($content) ? implode(PHP_EOL, $content) : $content;
            $this->fs->dumpFile($path, $content);
        }

        return $path;
    }

    /**
     * Create fixture tag with specified name and optional annotation.
     *
     * Annotated tags and lightweight tags have a different object
     * representation in git, therefore may need to be created explicitly for
     * some tests.
     *
     * @param string $path
     *   Optional path to the repository directory.
     * @param string $name
     *   Tag name.
     * @param bool   $annotate
     *   Optional flag to add random annotation to the tag. Defaults to FALSE.
     */
    protected function gitAddTag($path, $name, $annotate = false)
    {
        if ($annotate) {
            $this->runGitCommand(sprintf('tag -a %s -m "%s"', $name, 'Annotation for tag '.$name), $path);
        } else {
            $this->runGitCommand(sprintf('tag %s', $name), $path);
        }
    }

    /**
     * Assert that files exist in repository in specified branch.
     *
     * @param  string      $path
     *   Repository location.
     * @param array|string $files
     *   File or array of files.
     * @param string|null  $branch
     *   Optional branch. If set, will be checked out before assertion.
     *
     * @todo: Update arguments order and add assertion message.
     */
    protected function gitAssertFilesExist($path, $files, $branch = null)
    {
        $files = is_array($files) ? $files : [$files];
        if ($branch) {
            $this->gitCheckout($path, $branch);
        }
        foreach ($files as $file) {
            $this->assertFileExists($path.DIRECTORY_SEPARATOR.$file);
        }
    }

    /**
     * Assert that files do not exist in repository in specified branch.
     *
     * @param  string      $path
     *   Repository location.
     * @param array|string $files
     *   File or array of files.
     * @param string|null  $branch
     *   Optional branch. If set, will be checked out before assertion.
     */
    protected function gitAssertFilesNotExist($path, $files, $branch = null)
    {
        $files = is_array($files) ? $files : [$files];
        if ($branch) {
            $this->gitCheckout($path, $branch);
        }
        foreach ($files as $file) {
            $this->assertFileNotExists($path.DIRECTORY_SEPARATOR.$file);
        }
    }

    protected function assertFixtureCommits($count, $path, $branch, $additionalCommits = [])
    {
        $this->gitCheckout($path, $branch);
        $this->gitReset($path);

        $expectedCommits = [];
        $expectedFiles = [];
        for ($i = 1; $i <= $count; $i++) {
            $expectedCommits[] = sprintf('Commit number %s', $i);
            $expectedFiles[] = sprintf('%s.txt', $i);
        }
        $expectedCommits = array_merge($expectedCommits, $additionalCommits);

        $commits = $this->gitGetAllCommits($path);
        $this->assertEquals($commits, $expectedCommits, 'All fixture commits are present');

        $this->gitAssertFilesExist($this->fixtureRemoteRepo, $expectedFiles, $branch);
    }

    /**
     * Run Git command.
     *
     * @param string $args
     *   CLI arguments.
     * @param string $path
     *   Optional path to the repository. If not provided, fixture repository is
     *   used.
     *
     * @return array
     *   Array of output lines.
     */
    protected function runGitCommand($args, $path = null)
    {
        $path = $path ? $path : $this->fixtureSrcRepo;

        $command = 'git --no-pager';
        if (!empty($path)) {
            $command .= ' --git-dir='.$path.'/.git';
            $command .= ' --work-tree='.$path;
        }

        return $this->runCliCommand($command.' '.trim($args));
    }

    /**
     * Run Robo command.
     *
     * @param string $command
     *   Command string to run.
     * @param bool   $expectFail
     *   Flag to state that the command should fail.
     * @param string $roboBin
     *   Optional relative path to Robo binary.
     *
     * @return array Array of output lines.
     *   Array of output lines.
     */
    public function runRoboCommand($command, $expectFail = false, $roboBin = 'vendor/bin/robo')
    {
        if (!file_exists($roboBin)) {
            throw new RuntimeException(sprintf('Robo binary is not available at path "%s"', $roboBin));
        }

        try {
            $output = $this->runCliCommand($roboBin.' '.$command);
            if ($expectFail) {
                throw new AssertionFailedError('Command exited successfully but should not');
            }
        } catch (Error $error) {
            if (!$expectFail) {
                throw $error;
            }
            $output = explode(PHP_EOL, $error->getPrevious()->getMessage());
        }

        return $output;
    }

    /**
     * Run CLI command.
     *
     * @param string $command
     *   Command string to run.
     *
     * @return array
     *   Array of output lines.
     *
     * @throws \PHPUnit\Framework\Error\Error
     *   If commands exists with non-zero status.
     */
    protected function runCliCommand($command)
    {
        if ($this->printDebug) {
            print '==> '.$command.PHP_EOL;
        }
        exec($command.' 2>&1', $output, $code);

        if ($code != 0) {
            throw new Error(sprintf('Command "%s" exited with non-zero status', $command), $code, null, null, new Error(implode(PHP_EOL, $output), $code, null, null));
        }
        if ($this->printDebug) {
            print '====> '.implode($output, PHP_EOL);
        }

        return $output;
    }
}
