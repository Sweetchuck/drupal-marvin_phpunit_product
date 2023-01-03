<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_phpunit_product;

use Drush\Commands\marvin_phpunit\TestCommandsBase;
use Robo\Collection\CollectionBuilder;
use Sweetchuck\Robo\PHPUnit\PHPUnitTaskLoader;

class PhpunitCommands extends TestCommandsBase {

  use PHPUnitTaskLoader;

  /**
   * @command marvin:test:unit
   * @bootstrap none
   */
  public function runUnit(): CollectionBuilder {
    $testSuite = $this->getTestSuiteNamesByEnvironmentVariant();
    if ($testSuite === NULL) {
      // @todo Message.
      return $this->collectionBuilder();
    }

    $options = [];
    if ($testSuite) {
      $options['testSuite'] = $testSuite;
    }

    return $this->getTaskPhpUnitRun($options);
  }

  protected function getGroupNames(): array {
    return [];
  }

  protected function getPhpVariant(): array {
    return [
      'enabled' => TRUE,
      'binDir' => PHP_BINDIR,
      'phpExecutable' => PHP_BINDIR . '/php',
      'phpIni' => '',
      'cli' => NULL,
      'version' => [],
    ];
  }

}
