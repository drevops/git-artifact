<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Traits;

use DrevOps\GitArtifact\Tests\Unit\UnitTestCase;
use DrevOps\GitArtifact\Traits\FilesystemTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(FilesystemTrait::class)]
class FilesystemTraitTest extends UnitTestCase {

  /**
   * Test fsGetRootDir() returns PWD when set.
   */
  public function testFsGetRootDirWithPwd(): void {
    $test_class = $this->createTestClass();

    // Set PWD environment variable.
    $_SERVER['PWD'] = '/test/path';

    $result = $this->callProtectedMethod($test_class, 'fsGetRootDir');

    $this->assertEquals('/test/path', $result);

    // Clean up.
    unset($_SERVER['PWD']);
  }

  /**
   * Test fsGetRootDir() returns getcwd() when PWD not set.
   */
  public function testFsGetRootDirWithoutPwd(): void {
    $test_class = $this->createTestClass();

    // Unset PWD to force getcwd() usage.
    $original_pwd = $_SERVER['PWD'] ?? NULL;
    unset($_SERVER['PWD']);

    $result = $this->callProtectedMethod($test_class, 'fsGetRootDir');

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
    $test_class = $this->createTestClass();

    // Set PWD.
    $_SERVER['PWD'] = '/test/path1';

    $result1 = $this->callProtectedMethod($test_class, 'fsGetRootDir');

    // Change PWD.
    $_SERVER['PWD'] = '/test/path2';

    // Should still return cached value.
    $result2 = $this->callProtectedMethod($test_class, 'fsGetRootDir');

    $this->assertEquals('/test/path1', $result1);
    $this->assertEquals('/test/path1', $result2);

    // Clean up.
    unset($_SERVER['PWD']);
  }

  /**
   * Test fsAssertPathsExist() with existing path.
   */
  public function testFsAssertPathsExistWithExistingPath(): void {
    $test_class = $this->createTestClass();

    // Test with existing file.
    $tmp_file = tempnam(sys_get_temp_dir(), 'test');
    $result = $this->callProtectedMethod($test_class, 'fsAssertPathsExist', [$tmp_file, TRUE]);

    $this->assertTrue($result);

    // Clean up.
    unlink($tmp_file);
  }

  /**
   * Test fsAssertPathsExist() with non-existing path in strict mode.
   */
  public function testFsAssertPathsExistWithNonExistingPathStrict(): void {
    $test_class = $this->createTestClass();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('One of the files or directories does not exist');

    $this->callProtectedMethod($test_class, 'fsAssertPathsExist', ['/non/existing/path', TRUE]);
  }

  /**
   * Test fsAssertPathsExist() with non-existing path in non-strict mode.
   */
  public function testFsAssertPathsExistWithNonExistingPathNonStrict(): void {
    $test_class = $this->createTestClass();

    $result = $this->callProtectedMethod($test_class, 'fsAssertPathsExist', ['/non/existing/path', FALSE]);

    $this->assertFalse($result);
  }

  /**
   * Test fsAssertPathsExist() with array of paths.
   */
  public function testFsAssertPathsExistWithArrayOfPaths(): void {
    $test_class = $this->createTestClass();

    // Create temporary files.
    $tmp_file1 = tempnam(sys_get_temp_dir(), 'test1');
    $tmp_file2 = tempnam(sys_get_temp_dir(), 'test2');

    $result = $this->callProtectedMethod($test_class, 'fsAssertPathsExist', [[$tmp_file1, $tmp_file2], TRUE]);

    $this->assertTrue($result);

    // Clean up.
    unlink($tmp_file1);
    unlink($tmp_file2);
  }

  /**
   * Test fsGetAbsolutePath() with absolute path.
   */
  public function testFsGetAbsolutePathWithAbsolutePath(): void {
    $test_class = $this->createTestClass();

    $result = $this->callProtectedMethod($test_class, 'fsGetAbsolutePath', ['/absolute/path']);

    $this->assertEquals('/absolute/path', $result);
  }

  /**
   * Test fsGetAbsolutePath() with relative path.
   */
  public function testFsGetAbsolutePathWithRelativePath(): void {
    $test_class = $this->createTestClass();
    $this->setProtectedValue($test_class, 'fsRootDir', '/root/dir');

    $result = $this->callProtectedMethod($test_class, 'fsGetAbsolutePath', ['relative/path']);

    $this->assertEquals('/root/dir/relative/path', $result);
  }

  /**
   * Test fsGetAbsolutePath() with custom root.
   */
  public function testFsGetAbsolutePathWithCustomRoot(): void {
    $test_class = $this->createTestClass();

    $result = $this->callProtectedMethod($test_class, 'fsGetAbsolutePath', ['relative/path', '/custom/root']);

    $this->assertEquals('/custom/root/relative/path', $result);
  }

  #[DataProvider('dataProviderFsRealpath')]
  public function testFsRealpath(string $path, string $expected): void {
    $this->assertSame($expected, $this->callProtectedMethod($this->createTestClass(), 'fsRealpath', [$path]));
  }

  public static function dataProviderFsRealpath(): array {
    $cwd = getcwd();

    if ($cwd === FALSE) {
      throw new \RuntimeException('Failed to determine current working directory.');
    }

    do {
      $tmp_dir = sprintf('%s%s%s%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, 'unit', rand(100000, getrandmax()));
    } while (!mkdir($tmp_dir, 0755, TRUE));

    $tmp_realpath = realpath($tmp_dir) ?: $tmp_dir;

    $symlink_target = $tmp_realpath . DIRECTORY_SEPARATOR . 'real_file.txt';
    $symlink_path = $tmp_realpath . DIRECTORY_SEPARATOR . 'symlink.txt';

    // Create a real file and a symlink for testing.
    file_put_contents($symlink_target, 'test');
    if (!file_exists($symlink_path)) {
      symlink($symlink_target, $symlink_path);
    }

    return [
      // Absolute paths remain unchanged.
      ['/var/www/file.txt', '/var/www/file.txt'],

      // Relative path resolved from current working directory.
      ['file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'],

      // Parent directory resolution.
      ['../file.txt', dirname($cwd) . DIRECTORY_SEPARATOR . 'file.txt'],
      ['./file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'],

      // Temporary directory resolution.
      [$tmp_dir . DIRECTORY_SEPARATOR . 'file.txt', $tmp_realpath . DIRECTORY_SEPARATOR . 'file.txt'],

      // Symlink resolution.
      [$symlink_path, $symlink_target],
    ];
  }

  protected function createTestClass(): object {
    return new class() {

      use FilesystemTrait;

      public function __construct() {
        $this->fs = new Filesystem();
      }

    };
  }

}
