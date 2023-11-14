<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\source;

use Drupal\file\Plugin\migrate\source\d7\File as CoreFile;

/**
 * The 'kci_migrate_file' source plugin.
 *
 * @MigrateSource(
 *   id = "kci_migrate_file",
 *   source_module = "file"
 * )
 */
class File extends CoreFile {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    if (isset($this->configuration['file_type'])) {
      $types = $this->configuration['file_type'];
      if (!is_array($types)) {
        $types = [$types];
      }

      $query->condition('type', $types, 'IN');
    }
    if (isset($this->configuration['file_mime'])) {
      $mimeTypes = $this->configuration['file_mime'];
      if (!is_array($mimeTypes)) {
        $mimeTypes = [$mimeTypes];
      }

      $query->condition('filemime', $mimeTypes, 'IN');
    }
    if (isset($this->configuration['filename'])) {
      $query->condition('filename', $this->configuration['filename']);
    }
    return $query;
  }

}
