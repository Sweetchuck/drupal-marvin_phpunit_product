<?php

declare(strict_types=1);

namespace Drush\Commands\marvin_phpunit_product;

use Drupal\marvin\CommandEvent as BaseCommandEvent;
use Drupal\marvin\ContainerInitializer;
use Drupal\marvin\Test\CommandEvent as TestCommandEvent;
use Drupal\marvin\MarvinTaskDefinitionCommandTrait;
use Drupal\marvin\Utils;
use Drupal\marvin_git\GitHook\CommandEvent as GitHookCommandEvent;
use Drupal\marvin_product\CommandsBaseTrait;
use Drush\Attributes\Bootstrap as CliBootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Robo\Collection\CallableTask;
use Robo\Collection\Tasks as LoopTaskLoader;
use Robo\Contract\BuilderAwareInterface;
use Robo\State\Data as RoboState;
use Robo\Task\Base\Tasks as BaseTaskLoader;
use Robo\Task\File\Tasks as FileTaskLoader;
use Robo\TaskAccessor;
use Sweetchuck\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Runs PHPUnit for the given packages.',
)]
#[CliBootstrap(level: DrupalBootLevels::NONE)]
class MarvinTestPhpunitCommand extends Command implements BuilderAwareInterface {

  use AutowireTrait {
    create as protected autowireCreate;
  }
  use TaskAccessor;
  use BaseTaskLoader;
  use FileTaskLoader;
  use LoopTaskLoader;
  use CommandsBaseTrait;
  use MarvinTaskDefinitionCommandTrait;

  public const string NAME = 'marvin:test:phpunit';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    ContainerInitializer::initialize($container);

    return self::autowireCreate($container);
  }

  public function __construct(
    #[Autowire(Filesystem::class)]
    protected Filesystem $fs,
    #[Autowire('config')]
    protected DrushConfig $drushConfig,
    #[Autowire('eventDispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    #[Autowire(StringUtils::class)]
    protected StringUtils $stringUtils,
    #[Autowire(Utils::class)]
    protected Utils $utils,
    #[Autowire(LoggerInterface::class)]
    protected LoggerInterface $logger,
    #[Autowire(ContainerInterface::class)]
    protected ContainerInterface $container,
  ) {
    parent::__construct();

    $this->eventDispatcher->addListener(
      TestCommandEvent::EVENT_RUN_TASKS_COLLECT,
      $this->onEventMarvinTestTasksCollect(...),
    );

    $this->eventDispatcher->addListener(
      GitHookCommandEvent::EVENT_PRE_COMMIT_TASKS_COLLECT,
      $this->onEventMarvinGitHookPreCommitTasksCollect(...),
    );
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  protected function configure(): void {
    parent::configure();
    $this
      ->addArgument(
        'packageNames',
        InputArgument::IS_ARRAY,
        'Package names. Run "drush marvin:mde:list" to see the list of available packages.',
      )
      ->addUsage('First ::addUsage() goes into /dev/null')
      ->addUsage('my_module_01 drupal/my_module_02');
  }

  #[\Override]
  public function execute(InputInterface $input, OutputInterface $output): int {
    $event = new TestCommandEvent(
      $input,
      $output,
      NULL,
      $this->collectionBuilder(),
      [],
    );
    $event->taskDefinitions += $this->getTaskDefsInitStateDataBase($event);
    $event->taskDefinitions += $this->getTaskDefsInitStateDataCustom($event);
    $event->taskDefinitions += $this->getTaskDefsRunPhpunit($event);

    return $this->mtdRun(
      self::NAME,
      $event->collectionBuilder,
      $event->taskDefinitions,
    );
  }

  public function onEventMarvinTestTasksCollect(TestCommandEvent $event): void {
    $event->taskDefinitions += $this->getTaskDefsRunPhpunit($event);
  }

  public function onEventMarvinGitHookPreCommitTasksCollect(GitHookCommandEvent $event): void {
    $event->taskDefinitions += $this->getTaskDefsRunPhpunit($event);
  }

  protected function getTaskDefsInitStateDataCustom(BaseCommandEvent $event): array {
    $task = new CallableTask(
      function (RoboState $state): int {
        $state['primarySiteName'] = $this->getPrimarySiteName();

        return 0;
      },
      $event->collectionBuilder,
    );

    return [
      'Initialize-StateDataCustom.marvin_phpunit_incubator' => [
        'weight' => -998,
        'description' => 'Initialize state data.',
        'task' => $task,
      ],
    ];
  }

  protected function getTaskDefsRunPhpunit(BaseCommandEvent $event): array {
    $phpVariant = $this->getPhpVariant();
    $envVars = $phpVariant['command']['envVars'] ?? [];
    $envVars = array_filter(
      $envVars,
      fn($value) => $value !== NULL,
    );

    $command = [
      $phpVariant['command']['executable'],
      ...($phpVariant['command']['arguments'] ?? []),
    ];
    $command[] = 'vendor/bin/phpunit';

    $command = array_filter(
      $command,
      fn($item) => $item !== NULL,
    );
    array_walk(
      $command,
      fn(&$item) => $item = escapeshellarg($item),
    );

    $task = $this
      ->taskExec(implode(' ', $command))
      ->envVars($envVars);

    return [
      'Invoke-PhpUnit.marvin_phpunit_product' => [
        'weight' => 200,
        'description' => 'Executes PHPUnit.',
        'task' => $task,
      ],
    ];
  }

  protected function getPrimarySiteName(): string {
    return 'default';
  }

}
