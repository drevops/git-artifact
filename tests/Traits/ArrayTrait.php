<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Traits;

/**
 * Helpers to work with arrays.
 */
trait ArrayTrait {

  /**
   * Asserts that 2 arrays hold the same values, ignoring order.
   *
   * Nested arrays are compared recursively under the same key.
   *
   * @param array $expected
   *   Expected array.
   * @param array $array
   *   Array to assert.
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
