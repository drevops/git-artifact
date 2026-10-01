<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Exceptions;

use DrevOps\GitArtifact\Exceptions\BranchNotFoundException;
use DrevOps\GitArtifact\Exceptions\GitArtifactException;
use DrevOps\GitArtifact\Exceptions\GitException;
use DrevOps\GitArtifact\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BranchNotFoundException::class)]
#[CoversClass(GitException::class)]
#[CoversClass(GitArtifactException::class)]
class BranchNotFoundExceptionTest extends UnitTestCase {

  public function testCommitHashStorage(): void {
    $message = 'Test message';
    $commit_hash = 'abc123def456';

    $exception = new BranchNotFoundException($message, $commit_hash);

    $this->assertEquals($message, $exception->getMessage());
    $this->assertEquals($commit_hash, $exception->getCommitHash());
    $this->assertEquals(0, $exception->getCode());
  }

  public function testDefaultValues(): void {
    $exception = new BranchNotFoundException();

    $this->assertEquals('Unable to determine source branch', $exception->getMessage());
    $this->assertEquals('', $exception->getCommitHash());
    $this->assertEquals(0, $exception->getCode());
  }

  public function testInheritance(): void {
    $exception = new BranchNotFoundException();

    $this->assertInstanceOf(GitException::class, $exception);
    $this->assertInstanceOf(GitArtifactException::class, $exception);
    $this->assertInstanceOf(\RuntimeException::class, $exception);
  }

  public function testWithPreviousException(): void {
    $previous = new \Exception('Previous exception');
    $exception = new BranchNotFoundException('Test message', 'abc123', $previous);

    $this->assertEquals($previous, $exception->getPrevious());
  }

}
