<?php

declare(strict_types = 1);

namespace DrevOps\Robo\Tests\Unit;

/**
 * Class ExcludeTest.
 *
 * @group unit
 *
 * @covers \DrevOps\Robo\ArtifactTrait
 */
class ExcludeTest extends AbstractUnitTestCase
{

    /**
     * @throws \ReflectionException
     */
    public function testExcludeExists(): void
    {
        $this->createFixtureExcludeFile();

        $actual = $this->callProtectedMethod($this->mock, 'localExcludeExists', [$this->fixtureDir]);

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
    public function testExcludeEmpty(array $lines, bool $strict, bool $expected): void
    {
        $this->createFixtureExcludeFile(implode(PHP_EOL, $lines));

        $actual = $this->callProtectedMethod($this->mock, 'localExcludeEmpty', [$this->fixtureDir, $strict]);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array<mixed>
     *   Data provider.
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public static function dataProviderExcludeEmpty(): array
    {
        return [
            // Empty file.
            [
                [], true, true,
            ],
            [
                [], false, true,
            ],

            // Spaces single line.
            [
                [
                    '  ',
                ], true, false,
            ],

            [
                [
                    '  ',
                ], false, true,
            ],

            // Spaces.
            [
                [
                    '  ',
                    '  ',
                ], true, false,
            ],

            [
                [
                    '  ',
                    '  ',
                ], false, true,
            ],

            // Spaces, comments.
            [
                [
                    '  ',
                    '#comment  ',
                    '  ',
                ], true, false,
            ],

            [
                [
                    '  ',
                    '#comment  ',
                    '  ',
                ], false, true,
            ],

            // Spaces, padded comments.
            [
                [
                    '  ',
                    '   #comment  ',
                    '  ',
                ], true, false,
            ],

            [
                [
                    '  ',
                    '   #comment  ',
                    '  ',
                ], false, true,
            ],

            // Spaces, comments and valid content.
            [
                [
                    '  ',
                    '#comment  ',
                    'valid',
                    '  ',
                ], true, false,
            ],

            [
                [
                    '  ',
                    '#comment  ',
                    'valid',
                    '  ',
                ], false, false,
            ],

            // Spaces, inline comments and valid content.
            [
                [
                    '  ',
                    '#comment  ',
                    'valid',
                    'valid # other comment',
                    '  ',
                ], true, false,
            ],

            [
                [
                    '  ',
                    '#comment  ',
                    'valid',
                    'valid # other comment',
                    '  ',
                ], false, false,
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
    protected function createFixtureExcludeFile(string $contents = ''): string
    {
        return $this->gitCreateFixtureFile($this->fixtureDir.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'info', 'exclude', $contents);
    }
}
