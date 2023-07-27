<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_phpunit_product\Helper;

use Composer\Script\Event;
use Psr\Log\LoggerInterface;
use Sweetchuck\Utils\Filter\ArrayFilterFileSystemExists;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class ComposerScripts {

  /**
   * Composer event callback.
   */
  public static function postInstallCmd(Event $event): int {
    $self = new static($event);

    $self
      ->preparePhpunitXml()
      ->prepareProject();

    return 0;
  }

  /**
   * Composer event callback.
   */
  public static function postUpdateCmd(Event $event): int {
    $self = new static($event);

    $self
      ->preparePhpunitXml()
      ->prepareProject();

    return 0;
  }

  protected string $projectNameSnake = 'marvin_phpunit_product';

  protected string $projectNameDash = 'marvin-phpunit-product';

  /**
   * Current event.
   */
  protected Event $event;

  /**
   * CLI process callback.
   */
  protected \Closure $processCallbackWrapper;

  protected string $projectRoot = 'tests/fixtures/project_01';

  protected Filesystem $fs;

  protected LoggerInterface $logger;

  /**
   * Current working directory.
   */
  protected string $cwd = '.';

  protected function __construct(
    Event $event,
    ?LoggerInterface $logger = NULL,
    ?Filesystem $fs = NULL,
    string $cwd = '.'
  ) {
    $this->cwd = $cwd ?: '.';
    $this->event = $event;
    $this->logger = $logger ?: $this->createLogger();
    $this->fs = $fs ?: $this->createFilesystem();
  }

  protected function createLogger(): LoggerInterface {
    $io = $this->event->getIO();
    if ($io instanceof LoggerInterface) {
      return $io;
    }

    $verbosity = OutputInterface::VERBOSITY_NORMAL;
    if ($io->isDebug()) {
      $verbosity = OutputInterface::VERBOSITY_DEBUG;
    }
    elseif ($io->isVeryVerbose()) {
      $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE;
    }
    elseif ($io->isVerbose()) {
      $verbosity = OutputInterface::VERBOSITY_VERBOSE;
    }

    $output = new ConsoleOutput($verbosity, $io->isDecorated());

    return new ConsoleLogger($output);
  }

  protected function createFilesystem(): Filesystem {
    return new Filesystem();
  }

  protected function preparePhpunitXml(): static {
    $config = $this->event->getComposer()->getConfig();

    $phpunitExecutable = $config->get('bin-dir') . '/phpunit';
    if (!$this->fs->exists($phpunitExecutable)) {
      $this->logger->info('PHPUnit configuration file creation is skipped because phpunit/phpunit is not installed');

      return $this;
    }

    $dstFileName = "{$this->cwd}/phpunit.xml";
    if ($this->fs->exists($dstFileName)) {
      $this->logger->info('PHPUnit configuration file is already exists');

      return $this;
    }

    $srcFileName = "{$this->cwd}/phpunit.xml.dist";
    if (!$this->fs->exists($srcFileName)) {
      $this->logger->info("File not exists: '$srcFileName'");

      return $this;
    }

    $basePattern = '<env name="%s" value="%s"/>';
    $replacementPairs = [];
    foreach ($this->getPhpunitEnvVars() as $envVarName => $envVarValue) {
      $placeholder = sprintf("<!-- $basePattern -->", $envVarName, '');
      $replacementPairs[$placeholder] = sprintf($basePattern, $envVarName, $this->escapeXmlAttribute($envVarValue));
    }

    $content = $this->fileGetContents($srcFileName);
    $this->fs->dumpFile($dstFileName, strtr($content, $replacementPairs));

    return $this;
  }

  protected function prepareProject(): static {
    if (!$this->event->isDevMode()) {
      return $this;
    }

    $this
      ->prepareProjectComposerJson()
      ->prepareProjectPhpunitXml()
      ->prepareProjectSelf()
      ->prepareProjectDirs()
      ->prepareProjectSettingsPhp()
      ->prepareProjectDrushYml();

    return $this;
  }

  protected function prepareProjectComposerJson(): static {
    $content = [
      'name' => "drupal/{$this->projectNameDash}-tests-project_01",
      'description' => "drupal/{$this->projectNameDash}-tests-project_01",
      "license" => "proprietary",
      'type' => 'drupal-project',
      'config' => [
        'vendor-dir' => '../../../vendor',
        'bin-dir' => '../../../vendor/bin',
      ],
      'autoload-dev' => [
        'psr4' => [
          'V1\\P1\\Tests\\' => 'tests/src/',
        ],
      ],
      'extra' => [
        'installer-types' => [
          'bower-asset',
          'npm-asset',
        ],
        'installer-paths' => [
          'docroot/core' => [
            'type:drupal-core',
          ],
          'docroot/libraries/{$name}' => [
            'type:drupal-library',
            'type:bower-asset',
            'type:npm-asset',
          ],
          'docroot/modules/contrib/{$name}' => [
            'type:drupal-module',
          ],
          'docroot/profiles/contrib/{$name}' => [
            'type:drupal-profile',
          ],
          'docroot/themes/contrib/{$name}' => [
            'type:drupal-theme',
          ],
          'drush/Commands/contrib/{$name}' => [
            'type:drupal-drush',
          ],
        ],
        'enable-patching' => TRUE,
        'composer-exit-on-patch-failure' => TRUE,
        'patches' => [],
        'drupal-scaffold' => [
          'excludes' => [
            'sites/example.settings.local.php',
            '.csslintrc',
            '.editorconfig',
            '.eslintignore',
            '.eslintrc.json',
            '.gitattributes',
            '.ht.router.php',
            'web.config',
          ],
          'initial' => [
            'sites/default/default.services.yml' => 'sites/default/services.yml',
            'sites/default/default.settings.php' => 'sites/default/settings.php',
          ],
        ],
      ],
    ];

    $this->fs->dumpFile(
      "{$this->cwd}/{$this->projectRoot}/composer.json",
      json_encode($content, \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT) ?: '{}',
    );

    return $this;
  }

  protected function prepareProjectPhpunitXml(): static {
    $content = <<< 'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
      bootstrap="docroot/core/tests/bootstrap.php"
      colors="true"
      failOnWarning="true"
      beStrictAboutOutputDuringTests="true"
      beStrictAboutChangesToGlobalState="true">

      <php>
        <ini name="error_reporting" value="32767" />
        <ini name="memory_limit" value="-1" />

        <!--<env name="DTT_BASE_URL" value="https://local.drupal.hu" />-->
        <!--<env name="DTT_API_URL" value="http://chrome:9222" />-->
        <env name="DTT_SCREENSHOT_REPORT_DIRECTORY" value="../reports/human/functional-javascript" />

        <!--<env name="SIMPLETEST_BASE_URL" value="https://local.drupal.hu" />-->
        <!--<env name="SIMPLETEST_DB" value="mysql://root:root@db:3306/db" />-->

        <env name="BROWSERTEST_OUTPUT_DIRECTORY" value="./reports/human/browser_output" />
        <!--<env name="BROWSERTEST_OUTPUT_BASE_URL" value="https://local.drupal.hu" />-->

        <env name="SYMFONY_DEPRECATIONS_HELPER" value="/Drupal\\app(p|b|f|_.+?)\\/" />

        <!--<env name="MINK_DRIVER_CLASS" value="Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver" />-->
        <!--<env name="MINK_DRIVER_ARGS" value='["chrome", null, "http://127.0.0.1:4444/wd/hub"]' />-->
        <!--<env name="MINK_DRIVER_ARGS" value='["opera", null, "http://127.0.0.1:4444/wd/hub"]' />-->
        <!--<env name="MINK_DRIVER_ARGS_WEBDRIVER" value='["firefox", null, "http://127.0.0.1:4444/wd/hub"]'/>-->
      </php>

      <testsuites>
        <testsuite name="unit">
          <directory>./docroot/profiles/custom/*/tests/src/Unit/</directory>
          <directory>./docroot/modules/custom/*/tests/src/Unit/</directory>
          <directory>./docroot/themes/custom/*/tests/src/Unit/</directory>
          <directory>./drush/Commands/custom/*/tests/src/Unit/</directory>
          <directory>./tests/src/Unit/</directory>
        </testsuite>

        <testsuite name="functional-javascript">
          <!--<directory>./docroot/profiles/custom/*/tests/src/FunctionalJavascript/</directory>-->
          <!--<directory>./docroot/modules/custom/*/tests/src/FunctionalJavascript/</directory>-->
          <!--<directory>./docroot/themes/custom/*/tests/src/FunctionalJavascript/</directory>-->
          <!--<directory>./drush/Commands/custom/*/tests/src/FunctionalJavascript/</directory>-->
          <!--<directory>./tests/src/FunctionalJavascript/</directory>-->
        </testsuite>
      </testsuites>

      <!--<listeners>-->
        <!--<listener class="\Drupal\Tests\Listeners\DrupalListener" />-->
        <!-- The Symfony deprecation listener has to come after the Drupal deprecation listener -->
        <!--<listener class="\Symfony\Bridge\PhpUnit\SymfonyTestsListener" />-->
      <!--</listeners>-->

      <logging>
        <testdoxHtml outputFile="reports/human/result/phpunit.html" />
        <junit outputFile="reports/machine/junit/phpunit.xml" />
      </logging>

      <coverage>
        <include>
          <directory>./docroot/profiles/custom/</directory>
          <directory>./docroot/modules/custom/</directory>
          <directory>./docroot/themes/custom/</directory>
          <directory>./drush/Commands/custom/</directory>
        </include>
        <exclude>
          <directory>./docroot/profiles/custom/*/tests/</directory>
          <directory>./docroot/profiles/custom/*/node_modules/</directory>
          <directory>./docroot/modules/custom/*/tests/</directory>
          <directory>./docroot/modules/custom/*/node_modules/</directory>
          <directory>./docroot/themes/custom/*/tests/</directory>
          <directory>./docroot/themes/custom/*/node_modules/</directory>
          <directory>./drush/Commands/custom/*/tests/</directory>
        </exclude>

        <report>
          <text outputFile="php://stdout" />
          <html outputDirectory="reports/human/coverage/phpunit.html" />
          <clover outputFile="reports/machine/coverage/phpunit.clover.xml" />
          <php outputFile="reports/machine/coverage/phpunit.php" />
          <xml outputDirectory="reports/machine/coverage/phpunit.xml" />
        </report>
      </coverage>
    </phpunit>

    XML;

    $this->fs->dumpFile(
      "{$this->cwd}/{$this->projectRoot}/phpunit.xml.dist",
      $content,
    );

    return $this;
  }

  protected function prepareProjectSelf(): static {
    $dstDir = $this->getProjectSelfDestination();

    $relative = implode(
      '/',
      array_fill(
        0,
        substr_count($dstDir, '/') + 1,
        '..'
      )
    );

    $filesToSymlink = $this->getProjectSelfFilesToSymlink();
    $this->fs->mkdir($dstDir);
    foreach ($filesToSymlink as $fileToSymlink) {
      $this->fs->symlink("$relative/$fileToSymlink", "$dstDir/$fileToSymlink");
    }

    return $this;
  }

  protected function prepareProjectDirs(): static {
    $drushSutRoot = $this->projectRoot;

    $dirs = [
      "$drushSutRoot/docroot/libraries",
      "$drushSutRoot/docroot/profiles",
      "$drushSutRoot/docroot/themes",
      "$drushSutRoot/docroot/modules",
    ];
    $this->fs->mkdir($dirs, 0777 - umask());

    return $this;
  }

  protected function prepareProjectSettingsPhp(): static {
    $src = "{$this->projectRoot}/docroot/sites/default/default.settings.php";
    if (!$this->fs->exists($src)) {
      $this->logger->info(
        "File not exists: {fileName}",
        [
          'fileName' => $src,
        ]
      );

      return $this;
    }

    $dst = "{$this->projectRoot}/docroot/sites/default/settings.php";
    if ($this->fs->exists($dst)) {
      $this->logger->info(
        "File already exists: {fileName}",
        [
          'fileName' => $dst,
        ]
      );

      return $this;
    }

    $replacementPairs = [];
    $replacementPairs['$databases = [];'] = <<<'PHP'
$databases = [
  'default' => [
    'default' => [
      'driver' => 'sqlite',
      'database' => 'sites/default/db.default.default.sqlite',
    ],
  ],
];
PHP;

    $key = <<< 'TEXT'
 */

/**
 * Database settings:
TEXT;
    $replacementPairs[$key] = <<<'TEXT'
 */

/**
 * @var string $app_root
 * @var string $site_path
 */

/**
 * Database settings:
TEXT;

    $this->fs->dumpFile($dst, strtr($this->fileGetContents($src), $replacementPairs));

    return $this;
  }

  protected function prepareProjectDrushYml(): static {
    $filePath = "{$this->projectRoot}/drush/drush.yml";
    $this->fs->mkdir(Path::getDirectory($filePath));
    $this->fs->dumpFile(
      $filePath,
      Yaml::dump(
        [
          'drush' => [
            'paths' => [
              'config' => [
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/contrib/marvin/Commands/drush.yml",
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/contrib/marvin_product/Commands/drush.yml",
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/contrib/marvin_phpunit/Commands/drush.yml",
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/custom/{$this->projectNameSnake}/Commands/drush.yml",
              ],
              'include' => [
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/contrib/marvin",
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/contrib/marvin_product",
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/contrib/marvin_phpunit",
                "\${drush.vendor-dir}/../{$this->projectRoot}/drush/Commands/custom/{$this->projectNameSnake}",
              ],
            ],
          ],
          'marvin' => [
            'projectType' => 'integrationTest',
          ],
        ],
        99,
        2,
      ),
    );

    return $this;
  }

  protected function getProjectSelfDestination(): string {
    return "{$this->projectRoot}/drush/Commands/custom/" . $this->getComposerPackageName();
  }

  /**
   * @throws \Exception
   */
  protected function getComposerPackageName(): string {
    $parts = explode('/', $this->event->getComposer()->getPackage()->getName(), 2);
    if (empty($parts[1])) {
      throw new \Exception('Invalid package name', 1);
    }

    return $parts[1];
  }

  /**
   * @return string[]
   */
  protected function getProjectSelfFilesToSymlink(): array {
    $extra = $this->event->getComposer()->getPackage()->getExtra();
    $filesToSymLink = $extra[$this->projectNameSnake]['fixtures']['filesToSymlink'] ?? [];
    $filesToSymLink += $this->getProjectSelfFilesToSymlinkDefaults();

    $filesToSymLink = array_keys($filesToSymLink, TRUE, TRUE);

    $filter = new ArrayFilterFileSystemExists();
    $filter->setBaseDir($this->cwd);

    return array_filter($filesToSymLink, $filter);
  }

  /**
   * @return array<string, bool>
   */
  protected function getProjectSelfFilesToSymlinkDefaults(): array {
    return [
      'Commands' => TRUE,
      'src' => TRUE,
      'composer.json' => TRUE,
      'drush.services.yml' => TRUE,
      'drush9.services.yml' => TRUE,
      'drush10.services.yml' => TRUE,
      'drush11.services.yml' => TRUE,
      'drush12.services.yml' => TRUE,
    ];
  }

  /**
   * @phpstan-param array<string> $command
   */
  protected function processRun(string $workingDirectory, array $command): Process {
    $commandString = implode(' ', $command);
    $this->event->getIO()->write("Run '$commandString' in '$workingDirectory'");
    $process = new Process($command, NULL, NULL, NULL, 0);
    $process->setWorkingDirectory($workingDirectory);
    $process->run($this->processCallbackWrapper);

    return $process;
  }

  protected function processCallback(string $type, string $buffer): void {
    $type === Process::OUT ?
      $this->event->getIO()->write($buffer, FALSE)
      : $this->event->getIO()->writeError($buffer, FALSE);
  }

  protected function escapeXmlAttribute(string $value): string {
    return htmlentities($value, ENT_QUOTES);
  }

  protected function fileGetContents(string $fileName): string {
    $content = file_get_contents($fileName);
    if ($content === FALSE) {
      throw new \RuntimeException("File '$fileName' is not readable.", 1);
    }

    return $content;
  }

  /**
   * @phpstan-return array<string, string>
   */
  protected function getPhpunitEnvVars(): array {
    return [
      'SIMPLETEST_BASE_URL' => 'http://localhost:8888',
      'DTT_BASE_URL' => 'http://localhost:8888',
      'SIMPLETEST_DB' => "sqlite://sites/default/db.default.default.sqlite",
      'UNISH_DB_URL' => 'sqlite://sites/default/db.default.default.sqlite',
      'BROWSERTEST_OUTPUT_DIRECTORY' => realpath($this->cwd) . "/{$this->projectRoot}/docroot/sites/simpletest/browser_output",
    ];
  }

}
