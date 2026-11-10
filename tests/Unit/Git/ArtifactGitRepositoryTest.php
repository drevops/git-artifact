<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Git;

use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use DrevOps\GitArtifact\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ArtifactGitRepository::class)]
final class ArtifactGitRepositoryTest extends UnitTestCase {

  #[DataProvider('dataProviderIsValidRemote')]
  public function testIsValidRemote(string $url, string $type, bool $expect_exception, bool $expected): void {
    if ($expect_exception) {
      $this->expectException(\InvalidArgumentException::class);
      $this->expectExceptionMessage(sprintf('Invalid argument "%s" provided.', $type));
    }

    $url = $url === '<existing>' ? (new Filesystem())->tempnam(sys_get_temp_dir(), 'test') : $url;

    $actual = ArtifactGitRepository::isValidRemote($url, $type);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderIsValidRemote(): \Iterator {
    yield ['<existing>', 'any', FALSE, TRUE];
    yield ['<existing>', 'local', FALSE, TRUE];
    yield ['<existing>', 'external', FALSE, FALSE];
    yield ['<existing>', 'custom_type', TRUE, FALSE];
    // Negative tests.
    yield ['/path/non-existing', 'any', FALSE, FALSE];
    yield ['/path/non-existing', 'local', FALSE, FALSE];
    yield ['/path/non-existing', 'external', FALSE, FALSE];
    yield ['/path/non-existing', 'custom_type', TRUE, FALSE];
    yield ['git@github.com:user/repo.git', 'any', FALSE, TRUE];
    yield ['git@github.com:user/repo.git', 'external', FALSE, TRUE];
    yield ['git@github.com:user/repo.git', 'local', FALSE, FALSE];
    yield ['git@github.com:user/repo.git', 'custom_type', TRUE, FALSE];
    // Negative tests.
    yield ['git@github.com:user/repo', 'any', FALSE, FALSE];
    yield ['git@github.com:user/repo', 'external', FALSE, FALSE];
    yield ['git@github.com:user/repo', 'local', FALSE, FALSE];
    yield ['git@github.com:user/repo', 'custom_type', TRUE, FALSE];
    yield ['https://github.com/user/repo.git', 'any', FALSE, TRUE];
    yield ['https://github.com/user/repo.git', 'external', FALSE, TRUE];
    yield ['https://github.com/user/repo.git', 'local', FALSE, FALSE];
    yield ['https://github.com/user/repo.git', 'custom_type', TRUE, FALSE];
    // Negative tests.
    yield ['https://github.com/user/repo', 'any', FALSE, FALSE];
    yield ['https://github.com/user/repo', 'external', FALSE, FALSE];
    yield ['https://github.com/user/repo', 'local', FALSE, FALSE];
    yield ['https://github.com/user/repo', 'custom_type', TRUE, FALSE];
    yield ['http://github.com/user/repo.git', 'any', FALSE, TRUE];
    yield ['http://github.com/user/repo.git', 'external', FALSE, TRUE];
    yield ['http://github.com/user/repo.git', 'local', FALSE, FALSE];
    yield ['http://github.com/user/repo.git', 'custom_type', TRUE, FALSE];
    // Negative tests.
    yield ['http://github.com/user/repo', 'any', FALSE, FALSE];
    yield ['http://github.com/user/repo', 'external', FALSE, FALSE];
    yield ['http://github.com/user/repo', 'local', FALSE, FALSE];
    yield ['http://github.com/user/repo', 'custom_type', TRUE, FALSE];
    yield ['git://user/repo.git', 'any', FALSE, TRUE];
    yield ['git://user/repo.git', 'external', FALSE, TRUE];
    yield ['git://user/repo.git', 'local', FALSE, FALSE];
    yield ['git://user/repo.git', 'custom_type', TRUE, FALSE];
    // Negative tests.
    yield ['git://user/repo', 'any', FALSE, FALSE];
    yield ['git://user/repo', 'external', FALSE, FALSE];
    yield ['git://user/repo', 'local', FALSE, FALSE];
    yield ['git://user/repo', 'custom_type', TRUE, FALSE];
    yield ['ssh://git@github.com/user/repo.git', 'any', FALSE, TRUE];
    yield ['ssh://git@github.com/user/repo.git', 'external', FALSE, TRUE];
    yield ['ssh://git@github.com/user/repo.git', 'local', FALSE, FALSE];
    yield ['ssh://git@github.com/user/repo.git', 'custom_type', TRUE, FALSE];
    // Negative tests.
    yield ['ssh://git@github.com/user/repo', 'any', FALSE, FALSE];
    yield ['ssh://git@github.com/user/repo', 'external', FALSE, FALSE];
    yield ['ssh://git@github.com/user/repo', 'local', FALSE, FALSE];
    yield ['ssh://git@github.com/user/repo', 'custom_type', TRUE, FALSE];
  }

  #[DataProvider('dataProviderIsValidBranchName')]
  public function testIsValidBranchName(string $name, bool $expected): void {
    $this->assertSame($expected, ArtifactGitRepository::isValidBranchName($name));
  }

  public static function dataProviderIsValidBranchName(): \Iterator {
    yield ['', FALSE];
    yield [' ', FALSE];
    yield ["\n", FALSE];
    yield ['branch', TRUE];
    yield ['branch/sub', TRUE];
    yield ['branch/sub/subsub', TRUE];
    yield ['branch/*', FALSE];
    yield ['branch/sub/*', FALSE];
    yield ['branch/sub/subsub/*', FALSE];
    yield ['*/branch', FALSE];
    yield ['*.branch', FALSE];
    yield [':branch', FALSE];
    yield ['~branch', FALSE];
    yield ['?branch', FALSE];
    yield ['branch?', FALSE];
    yield ['branch/?', FALSE];
    yield ['branch//', FALSE];
    yield ['/branch', FALSE];
    yield ['//branch', FALSE];
    yield [str_repeat('a', 254), TRUE];
    yield [str_repeat('a', 255), FALSE];
    yield ['branch' . str_repeat('/sub', 255), FALSE];
  }

  /**
   * @param array<string, int> $branches
   *   Branches keyed to tip timestamps.
   * @param array<string> $patterns
   *   Glob or regex patterns to match eligible branches.
   * @param int $max_age
   *   Maximum allowed age in seconds.
   * @param int $now
   *   Reference Unix timestamp.
   * @param array<string> $protected_branches
   *   Protected branch names.
   * @param array<string> $expected
   *   Expected stale branch names.
   */
  #[DataProvider('dataProviderFilterStaleBranches')]
  public function testFilterStaleBranches(array $branches, array $patterns, int $max_age, int $now, array $protected_branches, array $expected): void {
    $this->assertSame($expected, ArtifactGitRepository::filterStaleBranches($branches, $patterns, $max_age, $now, $protected_branches));
  }

  /**
   * @return \Iterator<string, mixed>
   *   Test data.
   */
  public static function dataProviderFilterStaleBranches(): \Iterator {
    $now = 1000000000;
    $day = 86400;
    yield 'empty' => [[], ['*'], 3 * $day, $now, [], []];
    yield 'empty patterns' => [['deployment/a' => $now - 10 * $day], [], 3 * $day, $now, [], []];
    yield 'no pattern match' => [['feature/x' => $now - 10 * $day], ['deployment/*'], 3 * $day, $now, [], []];
    yield 'stale match' => [['deployment/a' => $now - 10 * $day], ['deployment/*'], 3 * $day, $now, [], ['deployment/a']];
    yield 'fresh kept' => [['deployment/a' => $now - $day], ['deployment/*'], 3 * $day, $now, [], []];
    yield 'boundary equal kept' => [['deployment/a' => $now - 3 * $day], ['deployment/*'], 3 * $day, $now, [], []];
    yield 'boundary just over' => [['deployment/a' => $now - 3 * $day - 1], ['deployment/*'], 3 * $day, $now, [], ['deployment/a']];
    yield 'protected excluded' => [['deployment/a' => $now - 10 * $day], ['deployment/*'], 3 * $day, $now, ['deployment/a'], []];
    yield 'future timestamp kept' => [['deployment/a' => $now + 5 * $day], ['deployment/*'], 3 * $day, $now, [], []];
    yield 'sorted output' => [
      ['deployment/c' => $now - 10 * $day, 'deployment/a' => $now - 10 * $day, 'deployment/b' => $now - 10 * $day],
      ['deployment/*'], 3 * $day, $now, [], ['deployment/a', 'deployment/b', 'deployment/c'],
    ];
    yield 'numeric branch name' => [['123' => $now - 10 * $day], ['*'], 3 * $day, $now, [], ['123']];
    yield 'wildcard all but protected' => [
      ['a' => $now - 10 * $day, 'b' => $now - 10 * $day],
      ['*'], 3 * $day, $now, ['b'], ['a'],
    ];
    yield 'multiple globs across prefixes' => [
      ['feature/a' => $now - 10 * $day, 'bugfix/b' => $now - 10 * $day, 'release/c' => $now - 10 * $day],
      ['feature/*', 'bugfix/*'], 3 * $day, $now, [], ['bugfix/b', 'feature/a'],
    ];
    yield 'overlapping globs dedup' => [
      ['feature/a' => $now - 10 * $day],
      ['feature/*', 'feature/a'], 3 * $day, $now, [], ['feature/a'],
    ];
    yield 'multiple globs none match' => [
      ['release/c' => $now - 10 * $day],
      ['feature/*', 'bugfix/*'], 3 * $day, $now, [], [],
    ];
    yield 'multiple globs protected excluded' => [
      ['feature/a' => $now - 10 * $day, 'bugfix/b' => $now - 10 * $day],
      ['feature/*', 'bugfix/*'], 3 * $day, $now, ['bugfix/b'], ['feature/a'],
    ];
    yield 'regex anchored match' => [
      ['feature/x' => $now - 10 * $day, 'xfeature/y' => $now - 10 * $day],
      ['/^feature\/.+$/'], 3 * $day, $now, [], ['feature/x'],
    ];
    yield 'regex excludes nested slash' => [
      ['feature/a' => $now - 10 * $day, 'feature/a/b' => $now - 10 * $day],
      ['/^feature\/[^\/]+$/'], 3 * $day, $now, [], ['feature/a'],
    ];
    yield 'glob spans nested slash' => [
      ['feature/a' => $now - 10 * $day, 'feature/a/b' => $now - 10 * $day],
      ['feature/*'], 3 * $day, $now, [], ['feature/a', 'feature/a/b'],
    ];
    yield 'glob and regex mixed' => [
      ['feature/a' => $now - 10 * $day, 'bugfix/b' => $now - 10 * $day, 'release/c' => $now - 10 * $day],
      ['feature/*', '/^bugfix\/.+$/'], 3 * $day, $now, [], ['bugfix/b', 'feature/a'],
    ];
  }

  /**
   * @param string $pattern
   *   Cleanup token.
   * @param bool $expected
   *   Whether the token is a regex literal.
   */
  #[DataProvider('dataProviderIsRegexPattern')]
  public function testIsRegexPattern(string $pattern, bool $expected): void {
    $this->assertSame($expected, ArtifactGitRepository::isRegexPattern($pattern));
  }

  /**
   * @return \Iterator<string, array{string, bool}>
   *   Test data.
   */
  public static function dataProviderIsRegexPattern(): \Iterator {
    yield 'empty' => ['', FALSE];
    yield 'glob wildcard' => ['*', FALSE];
    yield 'glob prefix' => ['feature/*', FALSE];
    yield 'glob question' => ['feature/?', FALSE];
    yield 'regex literal' => ['/^feature\/.+$/', TRUE];
    yield 'regex with flags' => ['/^feature\/.+$/i', TRUE];
    yield 'bare slash' => ['/', TRUE];
  }

  /**
   * @param string $pattern
   *   Regex literal to validate.
   * @param bool $expected
   *   Whether the pattern compiles.
   */
  #[DataProvider('dataProviderIsValidRegex')]
  public function testIsValidRegex(string $pattern, bool $expected): void {
    $this->assertSame($expected, ArtifactGitRepository::isValidRegex($pattern));
  }

  /**
   * @return \Iterator<string, array{string, bool}>
   *   Test data.
   */
  public static function dataProviderIsValidRegex(): \Iterator {
    yield 'valid anchored' => ['/^feature\/.+$/', TRUE];
    yield 'valid with flags' => ['/^feature\/.+$/i', TRUE];
    yield 'valid char class' => ['/^feature\/[^\/]+$/', TRUE];
    yield 'missing closing delimiter' => ['/^feature', FALSE];
    yield 'unmatched paren' => ['/(/', FALSE];
    yield 'unknown modifier' => ['/^a$/z', FALSE];
  }

}
