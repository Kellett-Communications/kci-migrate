<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Convert language "und" to "en".
 *
 * Examples:
 *
 * Example usage on it's own:
 * @code
 * process:
 *   langcode:
 *     plugin: language_und_to_en
 *     source: language
 * @endcode
 *
 * Example combined with default_value:
 * @code
 * process:
 * langcode:
 *   - plugin: default_value
 *     source: language
 *     default_value: "en"
 *   - plugin: language_und_to_en
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "language_und_to_en",
 * )
 */
class LanguageUndToEn extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($value) && $value == 'und') {
      $value = 'en';
    }
    return $value;
  }

}
