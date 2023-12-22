<?php

declare(strict_types = 1);

namespace DrevOps\Robo\Tests\Integration;

/**
 * Class ForcePushTest.
 *
 * @group integration
 */
class TokenTest extends AbstractIntegrationTestCase
{

    /**
     * @dataProvider dataProviderTokenProcess
     */
    public function testTokenProcess(string $string, string $name, string $replacement, string $expectedString): void
    {
        $mock = $this->prepareMock('DrevOps\Robo\TokenTrait', [
            'getToken'.ucfirst($name) => function ($prop) use ($replacement) {
                return !empty($prop) ? $replacement.' with property '.$prop : $replacement;
            },
        ]);

        $actual = $this->callProtectedMethod($mock, 'tokenProcess', [$string]);
        $this->assertEquals($expectedString, $actual);
    }

    /**
     * @return array<array<string>>
     *   Data provider.
     */
    public static function dataProviderTokenProcess(): array
    {
        return [
            [
                '',
                '',
                '',
                '',
            ],
            [
                '',
                'sometoken',
                'somevalue',
                '',
            ],
            [
                'string without a token',
                'sometoken',
                'somevalue',
                'string without a token',
            ],
            [
                'string with sometoken without delimiters',
                'sometoken',
                'somevalue',
                'string with sometoken without delimiters',
            ],
            [
                'string with [sometoken broken delimiters',
                'sometoken',
                'somevalue',
                'string with [sometoken broken delimiters',
            ],
            [
                'string with sometoken] broken delimiters',
                'sometoken',
                'somevalue',
                'string with sometoken] broken delimiters',
            ],
            // Proper token.
            [
                '[sometoken]',
                'sometoken',
                'somevalue',
                'somevalue',
            ],
            [
                'string with [sometoken] present',
                'sometoken',
                'somevalue',
                'string with somevalue present',
            ],
            // Token with properties.
            [
                'string with [sometoken:prop] present',
                'sometoken',
                'somevalue',
                'string with somevalue with property prop present',
            ],
            [
                'string with [sometoken:prop:otherprop] present',
                'sometoken',
                'somevalue',
                'string with somevalue with property prop:otherprop present',
            ],
        ];
    }

    /**
     * @dataProvider dataProviderHasToken
     */
    public function testHasToken(string $string, bool $hasToken): void
    {
        $mock = $this->prepareMock('DrevOps\Robo\TokenTrait');

        $actual = $this->callProtectedMethod($mock, 'hasToken', [$string]);
        $this->assertEquals($hasToken, $actual);
    }

    /**
     * @return array<mixed>
     *     Data provider.
     */
    public static function dataProviderHasToken(): array
    {
        return [
            ['notoken', false],
            ['[broken token', false],
            ['broken token]', false],
            ['[token]', true],
            ['string with [token] and other string', true],
            ['[token] and [otherttoken]', true],
        ];
    }
}
