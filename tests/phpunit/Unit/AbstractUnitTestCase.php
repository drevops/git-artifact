<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Tests\Traits\MockTrait;
use DrevOps\GitArtifact\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;

abstract class AbstractUnitTestCase extends TestCase {

  use MockTrait;
  use ReflectionTrait;

  /**
   * Asserts that two associative arrays are similar.
   *
   * Both arrays must have the same indexes with identical values
   * without respect to key ordering.
   *
   * @param array $expected
   *   Expected assert.
   * @param array $array
   *   The array want to assert.
   */
  protected function assertArraySimilar(array $expected, array $array): void {
    $this->assertEquals([], array_diff($array, $expected));
    $this->assertEquals([], array_diff_key($array, $expected));

    foreach ($expected as $key => $value) {
      if (is_array($value)) {
        $this->assertArraySimilar($value, $array[$key]);
      }
      else {
        $this->assertContains($value, $array);
      }
    }
  }

}
