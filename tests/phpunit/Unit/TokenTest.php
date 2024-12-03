<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Unit;

use DrevOps\GitArtifact\Traits\TokenTrait;

/**
 * Class ForcePushTest.
 *
 * @group integration
 *
 * @covers \DrevOps\GitArtifact\Traits\TokenTrait
 */
class TokenTest extends AbstractUnitTestCase {

  /**
   * @dataProvider dataProviderTokenExists
   */
  public function testTokenExists(string $string, bool $expected): void {
    $mock = $this->prepareMock(TokenTrait::class);

    $actual = $this->callProtectedMethod($mock, 'tokenExists', [$string]);
    $this->assertEquals($expected, $actual);
  }

  /**
   * @return array<mixed>
   *   Data provider.
   */
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
