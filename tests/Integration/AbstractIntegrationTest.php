<?php

namespace IntegratedExperts\Robo\Tests\Integration;

use IntegratedExperts\Robo\Tests\AbstractTest;

/**
 * Class AbstractTest
 *
 * @package IntegratedExperts\Robo\Tests
 */
abstract class AbstractIntegrationTest extends AbstractTest
{

    /**
     * Current branch.
     *
     * @var string
     */
    protected $currentBranch;

    /**
     * Artefact branch.
     *
     * @var string
     */
    protected $artefactBranch;

    /**
     * Remote name.
     *
     * @var string
     */
    protected $remote;

    /**
     * Mode in which the build will run.
     *
     * Passed as a value of the --mode option.
     *
     * @var string
     */
    protected $mode;

    /**
     * Current timestamp to run commands with.
     *
     * Used for generating internal tokens that could be based on time.
     *
     * @var int
     */
    protected $now;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->now = time();
        $this->currentBranch = 'master';
        $this->artefactBranch = 'master-artefact';
        $this->remote = 'dst';
    }

    /**
     * Build the artefact and assert success.
     *
     * @param string $args
     *   Optional string of arguments to pass to the build.
     * @param string $branch
     *   Optional --branch value. Defaults to 'testbranch'.
     * @param string $commit
     *   Optional commit string. Defaults to 'Deployment commit'.
     *
     * @return string
     *   Command output.
     */
    protected function assertBuildSuccess($args = '', $branch = 'testbranch', $commit = 'Deployment commit')
    {
        $output = $this->runBuild(sprintf('--push --branch=%s %s', $branch, $args));
        $this->assertContains(sprintf('Pushed branch "%s" with commit message "%s"', $branch, $commit), $output);

        return $output;
    }

    /**
     * Build the artefact and assert failure.
     *
     * @param string $args
     *   Optional string of arguments to pass to the build.
     * @param string $branch
     *   Optional --branch value. Defaults to 'testbranch'.
     * @param string $commit
     *   Optional commit string. Defaults to 'Deployment commit'.
     *
     * @return string
     *   Command output.
     */
    protected function assertBuildFailure($args = '', $branch = 'testbranch', $commit = 'Deployment commit')
    {
        $output = $this->runBuild(sprintf('--push --branch=%s %s', $branch, $args), true);
        $this->assertNotContains(sprintf('Pushed branch "%s" with commit message "%s"', $branch, $commit), $output);

        return $output;
    }

    /**
     * Run artefact build.
     *
     * @param string $args
     *   Additional arguments or options as a string.
     *
     * @return string
     *   Output string.
     */
    protected function runBuild($args = '', $expectFail = false)
    {
        if ($this->mode) {
            $args .= ' --mode='.$this->mode;
        }

        $output = $this->runRoboCommandTimestamped(sprintf('artefact --src=%s %s %s', $this->src, $this->dst, $args), $expectFail);

        return implode(PHP_EOL, $output);
    }

    /**
     * Run Robo command with current timestamp attached to artefact commands.
     *
     * @param string $command
     *   Command string to run.
     * @param bool   $expectFail
     *   Flag to state that the command should fail.
     *
     * @return array Array of output lines.
     *   Array of output lines.
     */
    protected function runRoboCommandTimestamped($command, $expectFail = false)
    {
        // Add --now option to all 'artefact' commands.
        if (strpos($command, 'artefact') === 0) {
            $command .= ' --now='.$this->now;
        }

        return $this->runRoboCommand($command, $expectFail);
    }

    /**
     * Assert current git branch.
     *
     * @param string $path
     *   Path to repository.
     *
     * @param        $branch
     *   Branch name to assert.
     */
    protected function assertGitCurrentBranch($path, $branch)
    {
        $currentBranch = $this->runGitCommand('rev-parse --abbrev-ref HEAD', $path);

        $this->assertContains($branch, $currentBranch, sprintf('Current branch is "%s"', $branch));
    }

    /**
     * Assert that there is no remote specified in git repository.
     *
     * @param string $path
     *   Path to repository.
     *
     * @param        $remote
     *   Remote name to assert.
     */
    protected function assertGitNoRemote($path, $remote)
    {
        $remotes = $this->runGitCommand('remote', $path);

        $this->assertNotContains($remote, $remotes, sprintf('Remote "%s" is not present"', $remote));
    }
}
