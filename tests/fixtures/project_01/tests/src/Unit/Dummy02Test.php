<?php

declare(strict_types = 1);

namespace V1\P1\Tests\Unit;

use PHPUnit\Framework\TestCase;

class Dummy02Test extends TestCase {

  public function testDummy(): void {
    static::assertSame('a', 'a');
  }

}
