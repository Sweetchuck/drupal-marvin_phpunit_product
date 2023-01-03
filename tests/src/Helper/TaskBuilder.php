<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_phpunit_product\Helper;

use Consolidation\AnnotatedCommand\Output\OutputAwareInterface;
use Consolidation\Config\ConfigAwareInterface;
use Consolidation\Config\ConfigAwareTrait;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Robo\Collection\CollectionBuilder;
use Robo\Common\OutputAwareTrait;
use Robo\Contract\BuilderAwareInterface;
use Robo\State\StateAwareInterface;
use Robo\State\StateAwareTrait;
use Robo\TaskAccessor;
use Sweetchuck\Robo\PHPUnit\PHPUnitTaskLoader;

class TaskBuilder implements
  BuilderAwareInterface,
  ConfigAwareInterface,
  ContainerAwareInterface,
  LoggerAwareInterface,
  OutputAwareInterface,
  StateAwareInterface {

  use TaskAccessor;
  use ConfigAwareTrait;
  use ContainerAwareTrait;
  use LoggerAwareTrait;
  use OutputAwareTrait;
  use StateAwareTrait;

  use PHPUnitTaskLoader {
    taskPHPUnitCoverageReportHtml as public;
    taskPHPUnitCoverageReportXml as public;
    taskPHPUnitListGroups as public;
    taskPHPUnitListSuites as public;
    taskPHPUnitListTests as public;
    taskPHPUnitListTestsXml as public;
    taskPHPUnitMergeCoveragePhp as public;
    taskPHPUnitParseConfigurationXml as public;
    taskPHPUnitRun as public;
    taskPHPUnitTestCasesToCsv as public;
    taskPHPUnitTestCasesToFileNames as public;
  }

  public function getLogger(): LoggerInterface {
    return $this->logger;
  }

  public function collectionBuilder(): CollectionBuilder {
    return CollectionBuilder::create($this->getContainer(), NULL);
  }

}
