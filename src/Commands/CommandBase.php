<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class with shared functions for our drush commands.
 */
abstract class CommandBase extends DrushCommands {

  /**
   * The app root.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * The site path.
   *
   * @var string
   */
  protected $sitePath;

  /**
   * The full site path.
   *
   * @var string
   */
  protected $fullSitePath;

  /**
   * The migration config.
   *
   * This will be loaded from the YML file.
   *
   * @var array
   */
  protected $migrateConfig = [];

  /**
   * Constructs a CommandBase object.
   *
   * @param string $appRoot
   *   The app root.
   * @param string $sitePath
   *   The site path.
   */
  public function __construct(string $appRoot, string $sitePath) {
    parent::__construct();
    $this->appRoot = $appRoot;
    $this->sitePath = $sitePath;
    $this->fullSitePath = $appRoot . '/' . $sitePath;
  }

  /**
   * Load the migration config.
   *
   * @throws \Exception
   */
  protected function loadConfig(): void {
    $configFile = $this->fullSitePath . '/kci-migrate.yml';
    if (!file_exists($configFile)) {
      throw new \Exception('Config file not found: ' . $configFile . '. Please create it first by copying example.kci-migrate.yml into ' . $this->sitePath . ' and customizing it.');
    }
    $this->migrateConfig = Yaml::parseFile($configFile);
  }

}
