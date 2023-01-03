<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_phpunit_product;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\marvin_phpunit\TestCommandsBase;
use Robo\Contract\TaskInterface;
use Sweetchuck\Robo\PHPUnit\PHPUnitTaskLoader;

class PhpunitCommands extends TestCommandsBase {

  use PHPUnitTaskLoader;

  /**
   * Runs PHPUnit tests.
   *
   * @phpstan-param array<string> $testSuites
   */
  #[CLI\Command(name: 'marvin:test:phpunit')]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  #[CLI\Argument(
    name: 'testSuites',
    description: 'PHPUnit testsuite names. Example: "unit".',
  )]
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
