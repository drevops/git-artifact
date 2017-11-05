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

    const FIXTURE_SRC_DIR = 'fixture_src_git';

    const FIXTURE_REMOTE_DIR = 'fixture_remote_git';

    /**
     * Current default branch.
     *
     * Used as a helper for test assertions.
     *
     * @var string
     */
    protected $defaultCurrentBranch;

    /**
     * Current timestamp to run commands with.
     *
     * @var int
     */
    protected $now;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->gitCommandTraitSetUp($this->getFixtureSrcDir(), $this->getFixtureRemoteDir(), $this->isDebug());

        $this->now = time();
        $this->defaultCurrentBranch = 'master';
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
     * @param string        $method
     *   Method name. Method can be static.
     * @param array         $args
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
     * Helper to prepare class mock.
     *
     * @param string $class
     *   Class name to generate the mock.
     * @param array  $methodsMap
     *   Optional array of methods and values, keyed by method name.
     * @param array  $args
     *   Optional array of constructor arguments. If omitted, a constructor will
     *   not be called.
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
            } else {
                $mock->expects($this->any())
                    ->method($method)
                    ->willReturn($value);
            }
        }

        return $mock;
    }

    /**
     * Get the path to the fixture source directory.
     *
     * @return string
     *   Path to the fixture directory.
     */
    protected function getFixtureSrcDir()
    {
        return getcwd().DIRECTORY_SEPARATOR.self::FIXTURE_SRC_DIR;
    }

    /**
     * Get the path to the fixture remote directory.
     *
     * @return string
     *   Path to the fixture directory.
     */
    protected function getFixtureRemoteDir()
    {
        return getcwd().DIRECTORY_SEPARATOR.self::FIXTURE_REMOTE_DIR;
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
    protected function runBuild($args = '')
    {
        $output = $this->runRoboCommand(sprintf('artefact --src=%s %s %s', $this->getFixtureSrcDir(), $this->getFixtureRemoteDir(), $args));

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
     * Check if testing framework was ran with --debug option.
     */
    protected function isDebug()
    {
        return in_array('--debug', $_SERVER['argv'], true);
    }
}
