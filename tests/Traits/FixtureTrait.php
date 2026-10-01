<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Traits;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Helpers to work with fixture files.
 */
trait FixtureTrait {

  /**
   * Fixture directory.
   */
  protected string $fixtureDir;

  /**
   * Initialize fixture directory.
   *
   * @param string|null $name
   *   Optional fixture name.
   * @param string|null $root
   *   Optional root directory.
   */
  protected function fixtureInit(?string $name = NULL, ?string $root = NULL): void {
    $name = $name ?? $this::class;
    $root = $root ?? sys_get_temp_dir();
    $this->fixtureDir = $root . DIRECTORY_SEPARATOR . $name . '-' . date('U') . '-' . getmypid();
  }

  /**
   * Create fixture file at provided path.
   *
   * @param string $path
   *   Directory to create the file in.
   * @param string $name
   *   Optional file name.
   * @param string|array<string> $content
   *   Optional file content.
   *
   * @return string
   *   Path to the created file.
   */
  protected function fixtureCreateFile(string $path, string $name = '', string|array $content = ''): string {
    $fs = new Filesystem();

    $name = $name !== '' && $name !== '0' ? $name : 'tmp' . rand(1000, 100000);
    $path = $path . DIRECTORY_SEPARATOR . $name;

    $dir = dirname($path);
    if (!empty($dir)) {
      $fs->mkdir($dir);
    }

    $fs->touch($path);
    if (!empty($content)) {
      $content = is_array($content) ? implode(PHP_EOL, $content) : $content;
      $fs->dumpFile($path, $content);
    }

    return $path;
  }

  /**
   * Remove fixture file at provided path.
   *
   * @param string $path
   *   Directory that contains the file.
   * @param string $name
   *   File name.
   */
  protected function fixtureRemoveFile(string $path, string $name): void {
    (new Filesystem())->remove($path . DIRECTORY_SEPARATOR . $name);
  }

}
