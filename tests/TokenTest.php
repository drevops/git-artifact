<?php

namespace IntegratedExperts\Robo\Tests;

/**
 * Class ForcePushTest.
 */
class TokenTest extends AbstractTest
{

    /**
     * @dataProvider dataProviderTokenProcess
     */
    public function testTokenProcess($string, $name, $replacement, $expectedString)
    {
        $mock = $this->prepareMock('IntegratedExperts\Robo\TokenTrait', [
            'getToken'.ucfirst($name) => function ($prop) use ($replacement) {
                return !empty($prop) ? $replacement.' with property '.$prop : $replacement;
            },
        ]);

        $actual = $this->callProtectedMethod($mock, 'tokenProcess', [$string]);
        $this->assertEquals($actual, $expectedString);
    }

    public function dataProviderTokenProcess()
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
    public function testHasToken($string, $hasToken)
    {
        $mock = $this->prepareMock('IntegratedExperts\Robo\TokenTrait');

        $actual = $this->callProtectedMethod($mock, 'hasToken', [$string]);
        $this->assertEquals($actual, $hasToken);
    }

    public function dataProviderHasToken()
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
