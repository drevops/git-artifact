<?php

namespace IntegratedExperts\Robo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class AbstractTest.
 *
 * Abstract test class used by all types of tests.
 *
 * @package IntegratedExperts\Robo\Tests
 */
abstract class AbstractTest extends TestCase
{

    use CommandTrait {
        CommandTrait::setUp as protected commandTraitSetUp;
        CommandTrait::tearDown as protected commandTraitTearDown;
        CommandTrait::runRoboCommand as public commandRunRoboCommand;
    }

    /**
     * File system.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * Fixture directory.
     *
     * @var string
     */
    protected $fixtureDir;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->fs = new Filesystem();

        $this->fixtureDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'robo_git_artefact';
        $this->fs->mkdir($this->fixtureDir);

        $this->commandTraitSetUp(
            $this->fixtureDir.DIRECTORY_SEPARATOR.'git_src',
            $this->fixtureDir.DIRECTORY_SEPARATOR.'git_remote',
            $this->isDebug()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->commandTraitTearDown();

        if ($this->fs->exists($this->fixtureDir)) {
            $this->fs->remove($this->fixtureDir);
        }
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
     * Check if testing framework was ran with --debug option.
     */
    protected function isDebug()
    {
        return in_array('--debug', $_SERVER['argv'], true);
    }
}
