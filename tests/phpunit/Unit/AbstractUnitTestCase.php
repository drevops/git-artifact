<?php

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Artifact;
use DrevOps\GitArtifact\Tests\AbstractTestCase;
use GitWrapper\GitWrapper;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class AbstractUnitTestCase.
 */
abstract class AbstractUnitTestCase extends AbstractTestCase {

  /**
   * Mock of the class.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $mock;

  protected function setUp(): void {
    parent::setUp();

    $mockBuilder = $this->getMockBuilder(Artifact::class);
    $fileSystem = new Filesystem();
    $gitWrapper = new GitWrapper();
    $output = new ConsoleOutput();

    $mockBuilder->setConstructorArgs([$gitWrapper, $fileSystem, $output]);
    $this->mock = $mockBuilder->getMock();
    $this->callProtectedMethod($this->mock, 'fsSetRootDir', [$this->fixtureDir]);
  }

}
