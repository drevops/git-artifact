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

  public function testFsGetRootDirWithPwd(): void {
    $test_class = $this->createTestClass();

    $_SERVER['PWD'] = '/test/path';

    $result = $this->callProtectedMethod($test_class, 'fsGetRootDir');

    $this->assertEquals('/test/path', $result);

    unset($_SERVER['PWD']);
  }

  public function testFsGetRootDirWithoutPwd(): void {
    $test_class = $this->createTestClass();

    $original_pwd = $_SERVER['PWD'] ?? NULL;
    unset($_SERVER['PWD']);

    $result = $this->callProtectedMethod($test_class, 'fsGetRootDir');

    $this->assertEquals(getcwd(), $result);

    if ($original_pwd !== NULL) {
      $_SERVER['PWD'] = $original_pwd;
    }
  }

  public function testFsGetRootDirCaching(): void {
    $test_class = $this->createTestClass();

    $_SERVER['PWD'] = '/test/path1';

    $result1 = $this->callProtectedMethod($test_class, 'fsGetRootDir');

    $_SERVER['PWD'] = '/test/path2';

    $result2 = $this->callProtectedMethod($test_class, 'fsGetRootDir');

    $this->assertEquals('/test/path1', $result1);
    $this->assertEquals('/test/path1', $result2);

    unset($_SERVER['PWD']);
  }

  public function testFsAssertPathsExistWithExistingPath(): void {
    $test_class = $this->createTestClass();

    $tmp_file = tempnam(sys_get_temp_dir(), 'test');
    $result = $this->callProtectedMethod($test_class, 'fsAssertPathsExist', [$tmp_file, TRUE]);

    $this->assertTrue($result);

    unlink($tmp_file);
  }

  public function testFsAssertPathsExistWithNonExistingPathStrict(): void {
    $test_class = $this->createTestClass();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('One of the files or directories does not exist');

    $this->callProtectedMethod($test_class, 'fsAssertPathsExist', ['/non/existing/path', TRUE]);
  }

  public function testFsAssertPathsExistWithNonExistingPathNonStrict(): void {
    $test_class = $this->createTestClass();

    $result = $this->callProtectedMethod($test_class, 'fsAssertPathsExist', ['/non/existing/path', FALSE]);

    $this->assertFalse($result);
  }

  public function testFsAssertPathsExistWithArrayOfPaths(): void {
    $test_class = $this->createTestClass();

    $tmp_file1 = tempnam(sys_get_temp_dir(), 'test1');
    $tmp_file2 = tempnam(sys_get_temp_dir(), 'test2');

    $result = $this->callProtectedMethod($test_class, 'fsAssertPathsExist', [[$tmp_file1, $tmp_file2], TRUE]);

    $this->assertTrue($result);

    unlink($tmp_file1);
    unlink($tmp_file2);
  }

  public function testFsGetAbsolutePathWithAbsolutePath(): void {
    $test_class = $this->createTestClass();

    $result = $this->callProtectedMethod($test_class, 'fsGetAbsolutePath', ['/absolute/path']);

    $this->assertEquals('/absolute/path', $result);
  }

  public function testFsGetAbsolutePathWithRelativePath(): void {
    $test_class = $this->createTestClass();
    $this->setProtectedValue($test_class, 'fsRootDir', '/root/dir');

    $result = $this->callProtectedMethod($test_class, 'fsGetAbsolutePath', ['relative/path']);

    $this->assertEquals('/root/dir/relative/path', $result);
  }

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

    $tmp_dir = NULL;

    for ($attempt = 0; $attempt < 10; $attempt++) {
      $candidate = sprintf('%s%s%s%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, 'unit', rand(100000, getrandmax()));

      if (mkdir($candidate, 0755, TRUE)) {
        $tmp_dir = $candidate;
        break;
      }
    }

    if ($tmp_dir === NULL) {
      throw new \RuntimeException('Failed to create a temporary directory.');
    }

    $tmp_realpath = realpath($tmp_dir) ?: $tmp_dir;

    $symlink_target = $tmp_realpath . DIRECTORY_SEPARATOR . 'real_file.txt';
    $symlink_path = $tmp_realpath . DIRECTORY_SEPARATOR . 'symlink.txt';

    file_put_contents($symlink_target, 'test');
    if (!file_exists($symlink_path)) {
      symlink($symlink_target, $symlink_path);
    }

    return [
      ['/var/www/file.txt', '/var/www/file.txt'],

      ['file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'],

      ['../file.txt', dirname($cwd) . DIRECTORY_SEPARATOR . 'file.txt'],
      ['./file.txt', $cwd . DIRECTORY_SEPARATOR . 'file.txt'],

      [$tmp_dir . DIRECTORY_SEPARATOR . 'file.txt', $tmp_realpath . DIRECTORY_SEPARATOR . 'file.txt'],

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
