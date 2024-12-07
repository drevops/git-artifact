<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Functional;

use DrevOps\GitArtifact\Traits\TokenTrait;

/**
 * Class ForcePushTest.
 *
 * @group integration
 *
 * @covers \DrevOps\GitArtifact\Traits\TokenTrait
 */
class TokenTest extends AbstractFunctionalTestCase {

  /**
   * @dataProvider dataProviderTokenProcess
   */
  public function testTokenProcess(string $string, string $expected): void {
    $class = new class() {
      use TokenTrait;

      public function getTokenSomeToken(?string $prop = NULL): string {
        return empty($prop) ? 'somevalue' : 'somevalue with property ' . $prop;
      }

    };

    $actual = $this->callProtectedMethod($class, 'tokenProcess', [$string]);
    $this->assertEquals($expected, $actual);
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
      ],
      [
        '',
        '',
      ],
      [
        'string without a token',
        'string without a token',
      ],
      [
        'string with sometoken without delimiters',
        'string with sometoken without delimiters',
      ],
      [
        'string with [sometoken broken delimiters',
        'string with [sometoken broken delimiters',
      ],
      [
        'string with sometoken] broken delimiters',
        'string with sometoken] broken delimiters',
      ],
      // Proper token.
      [
        '[sometoken]',
        'somevalue',
      ],
      [
        'string with [sometoken] present',
        'string with somevalue present',
      ],
      // Token with properties.
      [
        'string with [sometoken:prop] present',
        'string with somevalue with property prop present',
      ],
      [
        'string with [sometoken:prop:otherprop] present',
        'string with somevalue with property prop:otherprop present',
      ],
    ];
  }

}
