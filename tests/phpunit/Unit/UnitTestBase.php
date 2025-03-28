<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Tests\Traits\ArrayTrait;
use DrevOps\GitArtifact\Tests\Traits\MockTrait;
use DrevOps\GitArtifact\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\TestCase;

abstract class UnitTestBase extends TestCase {

  use MockTrait;
  use ReflectionTrait;
  use ArrayTrait;

}
