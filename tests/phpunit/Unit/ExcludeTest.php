<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

/**
 * Class ExcludeTest.
 *
 * @group unit
 *
 * @covers \DrevOps\GitArtifact\Artifact
 */
class ExcludeTest extends AbstractUnitTestCase {

  /**
   * @throws \ReflectionException
   */
  public function testExcludeExists(): void {
    $this->createFixtureExcludeFile();

    $actual = $this->callProtectedMethod($this->command, 'localExcludeExists', [$this->fixtureDir]);

    $this->assertTrue($actual);
  }

  /**
   * @param array<string> $lines
   *   Lines.
   * @param bool $strict
   *   Strict.
   * @param bool $expected
   *   Expected.
   *
   *
   * @dataProvider dataProviderExcludeEmpty
   *
   * @throws \ReflectionException
   */
  public function testExcludeEmpty(array $lines, bool $strict, bool $expected): void {
    $this->createFixtureExcludeFile(implode(PHP_EOL, $lines));

    $actual = $this->callProtectedMethod($this->command, 'localExcludeEmpty', [$this->fixtureDir, $strict]);

    $this->assertEquals($expected, $actual);
  }

  /**
   * @return array<mixed>
   *   Data provider.
   *
   * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
   */
  public static function dataProviderExcludeEmpty(): array {
    return [
          // Empty file.
          [
              [], TRUE, TRUE,
          ],
          [
              [], FALSE, TRUE,
          ],

          // Spaces single line.
          [
              [
                '  ',
              ], TRUE, FALSE,
          ],

          [
              [
                '  ',
              ], FALSE, TRUE,
          ],

          // Spaces.
          [
              [
                '  ',
                '  ',
              ], TRUE, FALSE,
          ],

          [
              [
                '  ',
                '  ',
              ], FALSE, TRUE,
          ],

          // Spaces, comments.
          [
              [
                '  ',
                '#comment  ',
                '  ',
              ], TRUE, FALSE,
          ],

          [
              [
                '  ',
                '#comment  ',
                '  ',
              ], FALSE, TRUE,
          ],

          // Spaces, padded comments.
          [
              [
                '  ',
                '   #comment  ',
                '  ',
              ], TRUE, FALSE,
          ],

          [
              [
                '  ',
                '   #comment  ',
                '  ',
              ], FALSE, TRUE,
          ],

          // Spaces, comments and valid content.
          [
              [
                '  ',
                '#comment  ',
                'valid',
                '  ',
              ], TRUE, FALSE,
          ],

          [
              [
                '  ',
                '#comment  ',
                'valid',
                '  ',
              ], FALSE, FALSE,
          ],

          // Spaces, inline comments and valid content.
          [
              [
                '  ',
                '#comment  ',
                'valid',
                'valid # other comment',
                '  ',
              ], TRUE, FALSE,
          ],

          [
              [
                '  ',
                '#comment  ',
                'valid',
                'valid # other comment',
                '  ',
              ], FALSE, FALSE,
          ],

    ];
  }

  /**
   * Helper to create an exclude file.
   *
   * @param string $contents
   *   Optional file contents.
   *
   * @return string
   *   Created file name.
   */
  protected function createFixtureExcludeFile(string $contents = ''): string {
    return $this->gitCreateFixtureFile($this->fixtureDir . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'info', 'exclude', $contents);
  }

}
