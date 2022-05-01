<?php

namespace DrevOps\Robo\Tests\Unit;

use DrevOps\Robo\Tests\AbstractTest;

/**
 * Class AbstractUnitTest.
 */
abstract class AbstractUnitTest extends AbstractTest
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

        $this->mock = $this->getMockForTrait('DrevOps\Robo\ArtefactTrait');
        $this->callProtectedMethod($this->mock, 'fsSetRootDir', [$this->fixtureDir]);
    }
}
