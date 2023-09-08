<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a kci_update_media_name_from_field_description plugin.
 *
 * This plugin should be used with file fields as part of the sub-process
 * for setting the target ID of the media entity from the migration lookup.
 * Running this plugin right after that will obtain the description, if any,
 * of the file from the source row and use it to update the media entity name.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     plugin: kci_update_media_name_from_field_description
 *     source: fid
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "kci_update_media_name_from_field_description"
 * )
 */
class UpdateMediaNameFromFileFieldDescription extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // The value is the new media entity ID.
    // First get the description from the source row.
    $description = $row->getSourceProperty('description');
    if (!empty($description)) {
      // Update the media entity name.
      $media = $this->entityTypeManager->getStorage('media')->load($value);
      if ($media) {
        $name = $media->label();
        if ($name != $description) {
          $media->set('name', $description);
          $media->save();
        }
      }
    }
    return $value;
  }

}
