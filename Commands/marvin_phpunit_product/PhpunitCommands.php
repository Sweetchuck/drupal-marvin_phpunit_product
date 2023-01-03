<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_phpunit_product;

use Drush\Commands\marvin_phpunit\TestCommandsBase;
use Robo\Contract\TaskInterface;
use Sweetchuck\Robo\PHPUnit\PHPUnitTaskLoader;

class PhpunitCommands extends TestCommandsBase {

  use PHPUnitTaskLoader;

  /**
   * Runs PHPUnit tests.
   *
   * @param array<string> $testSuites
   *   PHPUnit testsuite names. Example: "unit".
   *
   * @command marvin:test:phpunit
   * @bootstrap none
   */
  public function cmdMarvinTestPhpunitExecute(array $testSuites): TaskInterface {
    $testSuites = array_filter($testSuites, 'mb_strlen');
    if (!$testSuites) {
      $testSuites = $this->getTestSuiteNamesByEnvironmentVariant();
    }

    if ($testSuites === NULL) {
      // @todo Message.
      return $this->collectionBuilder();
    }

    $options = [];
    if ($testSuites) {
      $options['testSuite'] = $testSuites;
    }

    return $this->getTaskPhpUnitRun($options);
  }

}
