<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a skip_on_match plugin.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: skip_on_match
 *     source: foo
 *     target: foo
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "skip_on_match"
 * )
 */
final class SkipOnMatch extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition, MigrationInterface|null $migration = NULL) {
    $instance = new static(
      $configuration,
      $pluginId,
      $pluginDefinition
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    if (empty($configuration['source']) && !array_key_exists('source', $configuration)) {
      throw new \InvalidArgumentException('Skip on match plugin is missing source configuration.');
    }
    if (empty($configuration['target']) && !array_key_exists('target', $configuration)) {
      throw new \InvalidArgumentException('Skip on match plugin is missing target configuration.');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function row($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    $target_property = $this->configuration['target'];
    $source_property = $this->configuration['source'];
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      $target_property => $row->getSourceProperty($source_property),
    ]);
    if (!empty($users)) {
      throw new MigrateSkipRowException('Row skipped because the ' . $source_property . ' value (' . $value . ') already exists in the destination.');
    }
    return $value;
  }

}
