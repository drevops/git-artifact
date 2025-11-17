<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ArtifactCommand::class)]
class FilesystemTest extends UnitTestCase {

  #[DataProvider('dataProviderRealpath')]
  public function testRealpath(string $path, string $expected): void {
    $this->assertSame($expected, $this->callProtectedMethod(ArtifactCommand::class, 'fsRealpath', [$path]));
  }

  public static function dataProviderRealpath(): array {
    $cwd = getcwd();

    if ($cwd === FALSE) {
      throw new \RuntimeException('Failed to determine current working directory.');
    }

    do {
      $tmp_dir = sprintf('%s%s%s%s', sys_get_temp_dir(), DIRECTORY_SEPARATOR, 'unit', mt_rand(100000, mt_getrandmax()));
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

}
