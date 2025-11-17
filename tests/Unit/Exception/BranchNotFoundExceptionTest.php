<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Exception;

use DrevOps\GitArtifact\Exception\BranchNotFoundException;
use DrevOps\GitArtifact\Exception\GitArtifactException;
use DrevOps\GitArtifact\Exception\GitException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BranchNotFoundException::class)]
#[CoversClass(GitException::class)]
#[CoversClass(GitArtifactException::class)]
class BranchNotFoundExceptionTest extends TestCase {

  /**
   * Test exception stores and returns commit hash.
   */
  public function testCommitHashStorage(): void {
    $message = 'Test message';
    $commit_hash = 'abc123def456';

    $exception = new BranchNotFoundException($message, $commit_hash);

    $this->assertEquals($message, $exception->getMessage());
    $this->assertEquals($commit_hash, $exception->getCommitHash());
    $this->assertEquals(0, $exception->getCode());
  }

  /**
   * Test exception with default values.
   */
  public function testDefaultValues(): void {
    $exception = new BranchNotFoundException();

    $this->assertEquals('Unable to determine source branch', $exception->getMessage());
    $this->assertEquals('', $exception->getCommitHash());
    $this->assertEquals(0, $exception->getCode());
  }

  /**
   * Test exception inheritance.
   */
  public function testInheritance(): void {
    $exception = new BranchNotFoundException();

    $this->assertInstanceOf(GitException::class, $exception);
    $this->assertInstanceOf(GitArtifactException::class, $exception);
    $this->assertInstanceOf(\RuntimeException::class, $exception);
  }

  /**
   * Test exception with previous exception.
   */
  public function testWithPreviousException(): void {
    $previous = new \Exception('Previous exception');
    $exception = new BranchNotFoundException('Test message', 'abc123', $previous);

    $this->assertEquals($previous, $exception->getPrevious());
  }

}
