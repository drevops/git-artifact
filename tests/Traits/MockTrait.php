<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Provides a method to prepare a class mock.
 *
 * @phpstan-ignore trait.unused
 */
trait MockTrait {

  /**
   * Prepare a class mock.
   *
   * @param class-string $class
   *   Class name to generate the mock for.
   * @param array<string, scalar|\Closure> $methods
   *   Optional array of return values or closures, keyed by method name. A
   *   closure is called to produce the return value.
   * @param bool|array<mixed> $args
   *   Optional array of constructor arguments or FALSE to disable the original
   *   constructor. If omitted, an original constructor will be called.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   Mocked class.
   */
  protected function prepareMock(string $class, array $methods = [], array|bool $args = []): MockObject {
    $methods = array_filter($methods, fn($value, $key): bool => !is_numeric($key), ARRAY_FILTER_USE_BOTH);

    if (!class_exists($class)) {
      throw new \InvalidArgumentException(sprintf('Class %s does not exist', $class));
    }

    $builder = $this->getMockBuilder($class);

    if (is_array($args) && !empty($args)) {
      $builder->enableOriginalConstructor()->setConstructorArgs($args);
    }
    elseif ($args === FALSE) {
      $builder->disableOriginalConstructor();
    }

    $method_names = array_values(array_filter(array_keys($methods), fn(string $method): bool => !empty($method)));
    if (!empty($method_names)) {
      $builder->onlyMethods($method_names);
    }
    $mock = $builder->getMock();

    foreach ($methods as $method => $value) {
      if (is_object($value) && str_contains($value::class, 'Callback')) {
        $mock->expects($this->any())->method($method)->willReturnCallback($value);
      }
      elseif (is_object($value) && str_contains($value::class, 'Closure')) {
        $mock->expects($this->any())->method($method)->willReturnCallback($value);
      }
      else {
        $mock->expects($this->any())->method($method)->willReturn($value);
      }
    }

    return $mock;
  }

}
