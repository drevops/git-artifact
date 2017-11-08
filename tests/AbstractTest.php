<?php

namespace IntegratedExperts\Robo\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Class AbstractTest
 *
 * @package IntegratedExperts\Robo\Tests
 */
abstract class AbstractTest extends TestCase
{

    use CommandTrait {
        CommandTrait::setUp as private gitCommandTraitSetUp;
        CommandTrait::tearDown as private gitCommandTraitTearDown;
        CommandTrait::runRoboCommand as private gitRunRoboCommand;
    }

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
        $this->gitCommandTraitSetUp(
            getcwd().DIRECTORY_SEPARATOR.'git_src',
            getcwd().DIRECTORY_SEPARATOR.'git_remote',
            $this->isDebug()
        );
        $this->now = time();
        $this->currentBranch = 'master';
        $this->artefactBranch = 'master-artefact';
        $this->remote = 'dst';
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->gitCommandTraitTearDown();
    }

    /*
     * Call protected methods on the class.
     *
     * @param object|string $object
     *   Object or class name to use for a method call.
     * @param string $method
     *   Method name. Method can be static.
     * @param array $args
     *   Array of arguments to pass to the method. To pass arguments by
     *   reference, pass them by reference as an element of this array.
     *
     * @return mixed
     *   Method result.
     */
    protected static function callProtectedMethod($object, $method, array $args = [])
    {
        $class = new \ReflectionClass(is_object($object) ? get_class($object) : $object);
        $method = $class->getMethod($method);
        $method->setAccessible(true);
        $object = $method->isStatic() ? null : $object;

        return $method->invokeArgs($object, $args);
    }

    /**
     * Set protected property value.
     *
     * @param object $object
     *   Object to set the value on.
     * @param string $property
     *   Property name to set the value. Property should exists in the object.
     * @param mixed  $value
     *   Value to set to the property.
     */
    protected static function setProtectedValue($object, $property, $value)
    {
        $class = new \ReflectionClass(get_class($object));
        $property = $class->getProperty($property);
        $property->setAccessible(true);

        $property->setValue($object, $value);
    }

    /**
     * Get protected value from the object.
     *
     * @param object $object
     *   Object to set the value on.
     * @param string $property
     *   Property name to get the value. Property should exists in the object.
     *
     * @return mixed
     *   Protected property value.
     */
    protected static function getProtectedValue($object, $property)
    {
        $class = new \ReflectionClass(get_class($object));
        $property = $class->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($class);
    }

    /**
     * Helper to prepare class or trait mock.
     *
     * @param string $class
     *   Class or trait name to generate the mock.
     * @param array  $methodsMap
     *   Optional array of methods and values, keyed by method name. Array
     *   elements can be return values, callbacks created with
     *   $this->returnCallback(), or closures.
     * @param array  $args
     *   Optional array of constructor arguments. If omitted, a constructor
     *   will not be called.
     *
     * @return object
     *   Mocked class.
     */
    protected function prepareMock($class, array $methodsMap = [], array $args = [])
    {
        $methods = array_keys($methodsMap);

        $reflectionClass = new \ReflectionClass($class);

        if ($reflectionClass->isAbstract()) {
            $mock = $this->getMockForAbstractClass($class, $args, '', !empty($args), true, true, $methods);
        } elseif ($reflectionClass->isTrait()) {
            $mock = $this->getMockForTrait($class, [], '', true, true, true, array_keys($methodsMap));
        } else {
            $mockBuilder = $this->getMockBuilder($class);
            if (!empty($args)) {
                $mockBuilder = $mockBuilder->enableOriginalConstructor()
                    ->setConstructorArgs($args);
            } else {
                $mockBuilder = $mockBuilder->disableOriginalConstructor();
            }
            $mock = $mockBuilder->setMethods($methods)
                ->getMock();
        }

        foreach ($methodsMap as $method => $value) {
            // Handle callback values differently.
            if (is_object($value) && strpos(get_class($value), 'Callback') !== false) {
                $mock->expects($this->any())
                    ->method($method)
                    ->will($value);
            } elseif (is_object($value) && strpos(get_class($value), 'Closure') !== false) {
                $mock->expects($this->any())
                    ->method($method)
                    ->will($this->returnCallback($value));
            } else {
                $mock->expects($this->any())
                    ->method($method)
                    ->willReturn($value);
            }
        }

        return $mock;
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

        $output = $this->runRoboCommand(sprintf('artefact --src=%s %s %s', $this->src, $this->dst, $args), $expectFail);

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
    protected function runRoboCommand($command, $expectFail = false)
    {
        // Add --now option to all 'artefact' commands.
        if (strpos($command, 'artefact') === 0) {
            $command .= ' --now='.$this->now;
        }

        return $this->gitRunRoboCommand($command, $expectFail);
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

    /**
     * Check if testing framework was ran with --debug option.
     */
    protected function isDebug()
    {
        return in_array('--debug', $_SERVER['argv'], true);
    }
}
