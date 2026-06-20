<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ArtifactGitRepository::class)]
class ArtifactGitRepositoryTest extends UnitTestCase {

  #[DataProvider('dataProviderIsValidRemote')]
  public function testIsValidRemote(string $url, string $type, bool $expect_exception, bool $expected): void {
    if ($expect_exception) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage(sprintf('Invalid argument "%s" provided', $type));
    }

    $url = $url === '<existing>' ? (new Filesystem())->tempnam(sys_get_temp_dir(), 'test') : $url;

    $actual = ArtifactGitRepository::isValidRemote($url, $type);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderIsValidRemote(): array {
    return [
      ['<existing>', 'any', FALSE, TRUE],
      ['<existing>', 'local', FALSE, TRUE],
      ['<existing>', 'external', FALSE, FALSE],
      ['<existing>', 'custom_type', TRUE, FALSE],
      // Negative tests.
      ['/path/non-existing', 'any', FALSE, FALSE],
      ['/path/non-existing', 'local', FALSE, FALSE],
      ['/path/non-existing', 'external', FALSE, FALSE],
      ['/path/non-existing', 'custom_type', TRUE, FALSE],

      ['git@github.com:user/repo.git', 'any', FALSE, TRUE],
      ['git@github.com:user/repo.git', 'external', FALSE, TRUE],
      ['git@github.com:user/repo.git', 'local', FALSE, FALSE],
      ['git@github.com:user/repo.git', 'custom_type', TRUE, FALSE],
      // Negative tests.
      ['git@github.com:user/repo', 'any', FALSE, FALSE],
      ['git@github.com:user/repo', 'external', FALSE, FALSE],
      ['git@github.com:user/repo', 'local', FALSE, FALSE],
      ['git@github.com:user/repo', 'custom_type', TRUE, FALSE],

      ['https://github.com/user/repo.git', 'any', FALSE, TRUE],
      ['https://github.com/user/repo.git', 'external', FALSE, TRUE],
      ['https://github.com/user/repo.git', 'local', FALSE, FALSE],
      ['https://github.com/user/repo.git', 'custom_type', TRUE, FALSE],
      // Negative tests.
      ['https://github.com/user/repo', 'any', FALSE, FALSE],
      ['https://github.com/user/repo', 'external', FALSE, FALSE],
      ['https://github.com/user/repo', 'local', FALSE, FALSE],
      ['https://github.com/user/repo', 'custom_type', TRUE, FALSE],

      ['http://github.com/user/repo.git', 'any', FALSE, TRUE],
      ['http://github.com/user/repo.git', 'external', FALSE, TRUE],
      ['http://github.com/user/repo.git', 'local', FALSE, FALSE],
      ['http://github.com/user/repo.git', 'custom_type', TRUE, FALSE],
      // Negative tests.
      ['http://github.com/user/repo', 'any', FALSE, FALSE],
      ['http://github.com/user/repo', 'external', FALSE, FALSE],
      ['http://github.com/user/repo', 'local', FALSE, FALSE],
      ['http://github.com/user/repo', 'custom_type', TRUE, FALSE],

      ['git://user/repo.git', 'any', FALSE, TRUE],
      ['git://user/repo.git', 'external', FALSE, TRUE],
      ['git://user/repo.git', 'local', FALSE, FALSE],
      ['git://user/repo.git', 'custom_type', TRUE, FALSE],
      // Negative tests.
      ['git://user/repo', 'any', FALSE, FALSE],
      ['git://user/repo', 'external', FALSE, FALSE],
      ['git://user/repo', 'local', FALSE, FALSE],
      ['git://user/repo', 'custom_type', TRUE, FALSE],

      ['ssh://git@github.com/user/repo.git', 'any', FALSE, TRUE],
      ['ssh://git@github.com/user/repo.git', 'external', FALSE, TRUE],
      ['ssh://git@github.com/user/repo.git', 'local', FALSE, FALSE],
      ['ssh://git@github.com/user/repo.git', 'custom_type', TRUE, FALSE],
      // Negative tests.
      ['ssh://git@github.com/user/repo', 'any', FALSE, FALSE],
      ['ssh://git@github.com/user/repo', 'external', FALSE, FALSE],
      ['ssh://git@github.com/user/repo', 'local', FALSE, FALSE],
      ['ssh://git@github.com/user/repo', 'custom_type', TRUE, FALSE],
    ];
  }

  #[DataProvider('dataProviderIsValidBranchName')]
  public function testIsValidBranchName(string $name, bool $expected): void {
    $this->assertEquals($expected, ArtifactGitRepository::isValidBranchName($name));
  }

  public static function dataProviderIsValidBranchName(): array {
    return [
      ['', FALSE],
      [' ', FALSE],
      ["\n", FALSE],
      ['branch', TRUE],
      ['branch/sub', TRUE],
      ['branch/sub/subsub', TRUE],
      ['branch/*', FALSE],
      ['branch/sub/*', FALSE],
      ['branch/sub/subsub/*', FALSE],
      ['*/branch', FALSE],
      ['*.branch', FALSE],
      [':branch', FALSE],
      ['~branch', FALSE],
      ['?branch', FALSE],
      ['branch?', FALSE],
      ['branch/?', FALSE],
      ['branch//', FALSE],
      ['/branch', FALSE],
      ['//branch', FALSE],
      // Long branch names.
      [str_repeat('a', 254), TRUE],
      [str_repeat('a', 255), FALSE],
      ['branch' . str_repeat('/sub', 255), FALSE],
    ];
  }

  /**
   * @param array<string, int> $branches
   *   Branches keyed to tip timestamps.
   * @param array<string> $protected_branches
   *   Protected branch names.
   * @param array<string> $expected
   *   Expected stale branch names.
   */
  #[DataProvider('dataProviderFilterStaleBranches')]
  public function testFilterStaleBranches(array $branches, string $pattern, int $max_age, int $now, array $protected_branches, array $expected): void {
    $this->assertSame($expected, ArtifactGitRepository::filterStaleBranches($branches, $pattern, $max_age, $now, $protected_branches));
  }

  /**
   * @return array<string, mixed>
   *   Test data.
   */
  public static function dataProviderFilterStaleBranches(): array {
    $now = 1000000000;
    $day = 86400;

    return [
      'empty' => [[], '*', 3 * $day, $now, [], []],
      'no pattern match' => [['feature/x' => $now - 10 * $day], 'deployment/*', 3 * $day, $now, [], []],
      'stale match' => [['deployment/a' => $now - 10 * $day], 'deployment/*', 3 * $day, $now, [], ['deployment/a']],
      'fresh kept' => [['deployment/a' => $now - $day], 'deployment/*', 3 * $day, $now, [], []],
      'boundary equal kept' => [['deployment/a' => $now - 3 * $day], 'deployment/*', 3 * $day, $now, [], []],
      'boundary just over' => [['deployment/a' => $now - 3 * $day - 1], 'deployment/*', 3 * $day, $now, [], ['deployment/a']],
      'protected excluded' => [['deployment/a' => $now - 10 * $day], 'deployment/*', 3 * $day, $now, ['deployment/a'], []],
      'future timestamp kept' => [['deployment/a' => $now + 5 * $day], 'deployment/*', 3 * $day, $now, [], []],
      'sorted output' => [
        ['deployment/c' => $now - 10 * $day, 'deployment/a' => $now - 10 * $day, 'deployment/b' => $now - 10 * $day],
        'deployment/*', 3 * $day, $now, [], ['deployment/a', 'deployment/b', 'deployment/c'],
      ],
      'numeric branch name' => [['123' => $now - 10 * $day], '*', 3 * $day, $now, [], ['123']],
      'wildcard all but protected' => [
        ['a' => $now - 10 * $day, 'b' => $now - 10 * $day],
        '*', 3 * $day, $now, ['b'], ['a'],
      ],
    ];
  }

}
