<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
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
    if (empty($configuration['target']) && !array_key_exists('target', $configuration)) {
      throw new \InvalidArgumentException('Skip on match plugin is missing target configuration.');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Skips the current row if the source value matches a target value.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value.
   */
  public function row($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    $target_property = $this->configuration['target'];
    if (!empty($this->configuration['entity_type'])) {
      $entity_type = $this->configuration['entity_type'];
    }
    else {
      throw new \InvalidArgumentException('Skip on match plugin is missing entity_type configuration.');
    }
    $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties([
      $target_property => $value,
    ]);
    if (!empty($entities)) {
      throw new MigrateSkipRowException('Row skipped because the value (' . $value . ') already exists in the destination.');
    }
    return $value;
  }

  /**
   * Stops processing the current property.
   *
   * Stops processing when input value matches a target value.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value.
   */
  public function process($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    $target_property = $this->configuration['target'];
    if (!empty($this->configuration['entity_type'])) {
      $entity_type = $this->configuration['entity_type'];
    }
    else {
      throw new \InvalidArgumentException('Skip on match plugin is missing entity_type configuration.');
    }
    $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties([
      $target_property => $value,
    ]);
    if (!empty($entities)) {
      throw new MigrateSkipProcessException();
    }
    return $value;
  }

}
