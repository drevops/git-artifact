<?php

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\ArtifactTrait;
use DrevOps\GitArtifact\Tests\AbstractTestCase;

/**
 * Class AbstractUnitTestCase.
 */
abstract class AbstractUnitTestCase extends AbstractTestCase
{

    /**
     * Mock of the class.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $mock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock = $this->getMockForTrait(ArtifactTrait::class);
        $this->callProtectedMethod($this->mock, 'fsSetRootDir', [$this->fixtureDir]);
    }
}
