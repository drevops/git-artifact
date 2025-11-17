<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Traits;

use DrevOps\GitArtifact\Traits\FilesystemTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilesystemTrait::class)]
class FilesystemTraitTest extends TestCase {

  /**
   * Test fsGetRootDir() returns PWD when set.
   */
  public function testFsGetRootDirWithPwd(): void {
    $test_class = new FilesystemTraitTestClass();

    // Set PWD environment variable.
    $_SERVER['PWD'] = '/test/path';

    $result = $test_class->callFsGetRootDir();

    $this->assertEquals('/test/path', $result);

    // Clean up.
    unset($_SERVER['PWD']);
  }

  /**
   * Test fsGetRootDir() returns getcwd() when PWD not set.
   */
  public function testFsGetRootDirWithoutPwd(): void {
    $test_class = new FilesystemTraitTestClass();

    // Unset PWD to force getcwd() usage.
    $original_pwd = $_SERVER['PWD'] ?? NULL;
    unset($_SERVER['PWD']);

    $result = $test_class->callFsGetRootDir();

    $this->assertEquals(getcwd(), $result);

    // Restore original PWD.
    if ($original_pwd !== NULL) {
      $_SERVER['PWD'] = $original_pwd;
    }
  }

  /**
   * Test fsGetRootDir() caches the result.
   */
  public function testFsGetRootDirCaching(): void {
    $test_class = new FilesystemTraitTestClass();

    // Set PWD.
    $_SERVER['PWD'] = '/test/path1';

    $result1 = $test_class->callFsGetRootDir();

    // Change PWD.
    $_SERVER['PWD'] = '/test/path2';

    // Should still return cached value.
    $result2 = $test_class->callFsGetRootDir();

    $this->assertEquals('/test/path1', $result1);
    $this->assertEquals('/test/path1', $result2);

    // Clean up.
    unset($_SERVER['PWD']);
  }

  /**
   * Test fsAssertPathsExist() with existing path.
   */
  public function testFsAssertPathsExistWithExistingPath(): void {
    $test_class = new FilesystemTraitTestClass();

    // Test with existing file.
    $temp_file = tempnam(sys_get_temp_dir(), 'test');
    $result = $test_class->callFsAssertPathsExist($temp_file, TRUE);

    $this->assertTrue($result);

    // Clean up.
    unlink($temp_file);
  }

  /**
   * Test fsAssertPathsExist() with non-existing path in strict mode.
   */
  public function testFsAssertPathsExistWithNonExistingPathStrict(): void {
    $test_class = new FilesystemTraitTestClass();

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('One of the files or directories does not exist');

    $test_class->callFsAssertPathsExist('/non/existing/path', TRUE);
  }

  /**
   * Test fsAssertPathsExist() with non-existing path in non-strict mode.
   */
  public function testFsAssertPathsExistWithNonExistingPathNonStrict(): void {
    $test_class = new FilesystemTraitTestClass();

    $result = $test_class->callFsAssertPathsExist('/non/existing/path', FALSE);

    $this->assertFalse($result);
  }

  /**
   * Test fsAssertPathsExist() with array of paths.
   */
  public function testFsAssertPathsExistWithArrayOfPaths(): void {
    $test_class = new FilesystemTraitTestClass();

    // Create temporary files.
    $temp_file1 = tempnam(sys_get_temp_dir(), 'test1');
    $temp_file2 = tempnam(sys_get_temp_dir(), 'test2');

    $result = $test_class->callFsAssertPathsExist([$temp_file1, $temp_file2], TRUE);

    $this->assertTrue($result);

    // Clean up.
    unlink($temp_file1);
    unlink($temp_file2);
  }

  /**
   * Test fsGetAbsolutePath() with absolute path.
   */
  public function testFsGetAbsolutePathWithAbsolutePath(): void {
    $test_class = new FilesystemTraitTestClass();

    $result = $test_class->callFsGetAbsolutePath('/absolute/path');

    $this->assertEquals('/absolute/path', $result);
  }

  /**
   * Test fsGetAbsolutePath() with relative path.
   */
  public function testFsGetAbsolutePathWithRelativePath(): void {
    $test_class = new FilesystemTraitTestClass();
    $test_class->setFsRootDir('/root/dir');

    $result = $test_class->callFsGetAbsolutePath('relative/path');

    $this->assertEquals('/root/dir/relative/path', $result);
  }

  /**
   * Test fsGetAbsolutePath() with custom root.
   */
  public function testFsGetAbsolutePathWithCustomRoot(): void {
    $test_class = new FilesystemTraitTestClass();

    $result = $test_class->callFsGetAbsolutePath('relative/path', '/custom/root');

    $this->assertEquals('/custom/root/relative/path', $result);
  }

}
