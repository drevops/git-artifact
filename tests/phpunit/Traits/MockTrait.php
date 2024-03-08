<?php

namespace DrevOps\GitArtifact\Tests\Traits;

/**
 * Trait MockTrait.
 *
 * This trait provides a method to prepare class mock.
 */
trait MockTrait
{

  /**
   * Helper to prepare class or trait mock.
   *
   * @param class-string $class
   *   Class or trait name to generate the mock.
   * @param array<string, \Closure> $methodsMap
   *   Optional array of methods and values, keyed by method name. Array
   *   elements can be return values, callbacks created with
   *   $this->returnCallback(), or closures.
   * @param array<mixed> $args
   *   Optional array of constructor arguments. If omitted, a constructor
   *   will not be called.
   *
   * @return object
   *   Mocked class.
   *
   * @throws \ReflectionException
   */
    protected function prepareMock(string $class, array $methodsMap = [], array $args = [])
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
          /* @todo setMethods method is not found on MockBuilder */
          /* @phpstan-ignore-next-line */
            $mock = $mockBuilder->setMethods($methods)
            ->getMock();
        }

        foreach ($methodsMap as $method => $value) {
          // Handle callback values differently.
            if (is_object($value) && str_contains($value::class, 'Callback')) {
                $mock->expects($this->any())
                ->method($method)
                ->will($value);
            } elseif (is_object($value) && str_contains($value::class, 'Closure')) {
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
}
