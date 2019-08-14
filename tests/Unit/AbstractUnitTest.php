<?php

namespace IntegratedExperts\Robo\Tests\Unit;

use IntegratedExperts\Robo\Tests\AbstractTest;

/**
 * Class AbstractUnitTest.
 */
class AbstractUnitTest extends AbstractTest
{

    /**
     * Mock of the class.
     *
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $mock;

    protected function setUp()
    {
        parent::setUp();

        $this->mock = $this->getMockForTrait('IntegratedExperts\Robo\ArtefactTrait');
        $this->callProtectedMethod($this->mock, 'fsSetRootDir', [$this->fixtureDir]);
    }
}
