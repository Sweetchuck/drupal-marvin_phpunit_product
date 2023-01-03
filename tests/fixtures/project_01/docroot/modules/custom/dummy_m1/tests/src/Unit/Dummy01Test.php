<?php

declare(strict_types = 1);

namespace Drupal\Tests\dummy_m1\Unit;

use PHPUnit\Framework\TestCase;

class Dummy01Test extends TestCase {

  public function testDummy(): void {
    static::assertSame('a', 'a');
  }

}
