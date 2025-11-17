<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Commands\ArtifactCommand;
use DrevOps\GitArtifact\Traits\TokenTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ArtifactCommand::class)]
class TokenTest extends UnitTestCase {

  #[DataProvider('dataProviderTokenProcess')]
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

  #[DataProvider('dataProviderTokenExists')]
  public function testTokenExists(string $string, bool $expected): void {
    $class = new class() {

      use TokenTrait;
    };

    $actual = $this->callProtectedMethod($class, 'tokenExists', [$string]);
    $this->assertEquals($expected, $actual);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderTokenExists(): array {
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
