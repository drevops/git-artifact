<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit\Traits;

use DrevOps\GitArtifact\Tests\Unit\UnitTestCase;
use DrevOps\GitArtifact\Traits\TokenTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversTrait(TokenTrait::class)]
final class TokenTraitTest extends UnitTestCase {

  #[DataProvider('dataProviderTokenProcess')]
  public function testTokenProcess(string $string, string $expected): void {
    $test_class = new class() {

      use TokenTrait;

      public function getTokenSomeToken(?string $prop = NULL): string {
        return empty($prop) ? 'somevalue' : 'somevalue with property ' . $prop;
      }

    };

    $actual = $this->callProtectedMethod($test_class, 'tokenProcess', [$string]);
    $this->assertEquals($expected, $actual);
  }

  public static function dataProviderTokenProcess(): \Iterator {
    yield [
      '',
      '',
    ];
    yield [
      '',
      '',
    ];
    yield [
      'string without a token',
      'string without a token',
    ];
    yield [
      'string with sometoken without delimiters',
      'string with sometoken without delimiters',
    ];
    yield [
      'string with [sometoken broken delimiters',
      'string with [sometoken broken delimiters',
    ];
    yield [
      'string with sometoken] broken delimiters',
      'string with sometoken] broken delimiters',
    ];
    yield [
      '[sometoken]',
      'somevalue',
    ];
    yield [
      'string with [sometoken] present',
      'string with somevalue present',
    ];
    yield [
      'string with [sometoken:prop] present',
      'string with somevalue with property prop present',
    ];
    yield [
      'string with [sometoken:prop:otherprop] present',
      'string with somevalue with property prop:otherprop present',
    ];
  }

  #[DataProvider('dataProviderTokenExists')]
  public function testTokenExists(string $string, bool $expected): void {
    $test_class = new class() {

      use TokenTrait;
    };

    $actual = $this->callProtectedMethod($test_class, 'tokenExists', [$string]);
    $this->assertSame($expected, $actual);
  }

  public static function dataProviderTokenExists(): \Iterator {
    yield ['notoken', FALSE];
    yield ['[broken token', FALSE];
    yield ['broken token]', FALSE];
    yield ['[token]', TRUE];
    yield ['string with [token] and other string', TRUE];
    yield ['[token] and [otherttoken]', TRUE];
  }

}
