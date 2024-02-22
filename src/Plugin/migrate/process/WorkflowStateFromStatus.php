<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Add sets workflow state value based on the status value.
 *
 * Example:
 *
 * @code
 * process:
 *   moderation_state:
 *     plugin: workflow_state_from_status
 *     source: status
 *     default_value: 'draft'
 *     states:
 *       0: 'archived'
 *       1: 'published'
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "workflow_state_from_status",
 * )
 */
class WorkflowStateFromStatus extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $states = $this->configuration['states'];
    $default_value = $this->configuration['default_value'];
    return $states[$value] ?? $default_value;
  }

}
