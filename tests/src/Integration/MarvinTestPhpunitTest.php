<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_phpunit_product\Integration;

/**
 * @group marvin
 * @group marvin_product
 * @group marvin_phpunit
 * @group marvin_phpunit_product
 * @group drush-command
 *
 * @covers \Drush\Commands\marvin_phpunit_product\PhpunitCommands
 */
class MarvinTestPhpunitTest extends UnishIntegrationTestCase {

  public function testTestPhpunitHelp(): void {
    $expected = [
      'stdError' => '',
      'stdOutput' => 'Runs PHPUnit tests.',
      'exitCode' => 0,
    ];

    $args = [];
    $options = $this->getCommonCommandLineOptions();
    $options['help'] = NULL;

    $this->drush(
      'marvin:test:phpunit',
      $args,
      $options,
      NULL,
      NULL,
      $expected['exitCode'],
      NULL,
      $this->getCommonCommandLineEnvVars(),
    );

    $actualStdError = $this->getErrorOutput();
    $actualStdOutput = $this->getOutput();

    static::assertStringContainsString($expected['stdError'], $actualStdError, 'StdError');
    static::assertStringContainsString($expected['stdOutput'], $actualStdOutput, 'StdOutput');
  }

  public function testTestPhpunitRun(): void {
    $expected = [
      'stdError' => '',
      'stdOutput' => 'OK (2 tests, 2 assertions)',
      'exitCode' => 0,
    ];

    $args = [];
    $options = $this->getCommonCommandLineOptions();

    $this->drush(
      'marvin:test:phpunit',
      $args,
      $options,
      NULL,
      $this->getProjectRootDir(),
      $expected['exitCode'],
      NULL,
      $this->getCommonCommandLineEnvVars(),
    );

    $actualStdError = $this->getErrorOutput();
    $actualStdOutput = $this->getOutput();

    static::assertStringContainsString($expected['stdError'], $actualStdError, 'StdError');
    static::assertStringContainsString($expected['stdOutput'], $actualStdOutput, 'StdOutput');
  }

}
