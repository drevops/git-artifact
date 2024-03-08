<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\TokenTrait;

/**
 * Class ForcePushTest.
 *
 * @group integration
 *
 * @covers \DrevOps\GitArtifact\TokenTrait
 */
class TokenTest extends AbstractFunctionalTestCase {

  /**
   * @dataProvider dataProviderTokenProcess
   */
  public function testTokenProcess(string $string, string $name, string $replacement, string $expectedString): void {
    $mock = $this->prepareMock(TokenTrait::class, [
      'getToken' . ucfirst($name) => static function (?string $prop) use ($replacement) : string {
              return empty($prop) ? $replacement : $replacement . ' with property ' . $prop;
      },
    ]);

    $actual = $this->callProtectedMethod($mock, 'tokenProcess', [$string]);
    $this->assertEquals($expectedString, $actual);
  }

  /**
   * @return array<array<string>>
   *   Data provider.
   */
  public static function dataProviderTokenProcess(): array {
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
  public function testHasToken(string $string, bool $hasToken): void {
    $mock = $this->prepareMock(TokenTrait::class);

    $actual = $this->callProtectedMethod($mock, 'hasToken', [$string]);
    $this->assertEquals($hasToken, $actual);
  }

  /**
   * @return array<mixed>
   *   Data provider.
   */
  public static function dataProviderHasToken(): array {
    return [
          ['notoken', FALSE],
          ['[broken token', FALSE],
          ['broken token]', FALSE],
          ['[token]', TRUE],
          ['string with [token] and other string', TRUE],
          ['[token] and [otherttoken]', TRUE],
    ];
  }

}
