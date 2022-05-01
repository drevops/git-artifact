<?php

namespace DrevOps\Robo\Tests\Unit;

/**
 * Class ExcludeTest.
 *
 * @group unit
 */
class ExcludeTest extends AbstractUnitTest
{

    public function testExcludeExists()
    {
        $this->createFixtureExcludeFile();

        $actual = $this->callProtectedMethod($this->mock, 'localExcludeExists', [$this->fixtureDir]);

        $this->assertTrue($actual);
    }


    /**
     * @dataProvider dataProviderExcludeEmpty
     */
    public function testExcludeEmpty($lines, $strict, $expected)
    {
        $this->createFixtureExcludeFile(implode(PHP_EOL, $lines));

        $actual = $this->callProtectedMethod($this->mock, 'localExcludeEmpty', [$this->fixtureDir, $strict]);

        $this->assertEquals($expected, $actual);
    }

    public function dataProviderExcludeEmpty()
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
    protected function createFixtureExcludeFile($contents = '')
    {
        return $this->gitCreateFixtureFile($this->fixtureDir.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'info', 'exclude', $contents);
    }
}
