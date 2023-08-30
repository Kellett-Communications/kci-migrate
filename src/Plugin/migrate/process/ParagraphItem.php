<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Adds paragraphs to node content.
 *
 * This is just an example in case we need it. However, it is
 * generally simpler to migrate paragraphs on their own first
 * with another migration yml file and then use those to migrate
 * them into the appropriate node content field.
 *
 * Example:
 *
 * @code
 * process:
 *   field_paragraphs:
 *     plugin: kci_paragraph
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "kci_paragraph",
 * )
 */
class ParagraphItem extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $node = $row->getSource();
    $nodeId = !empty($node['nid']) ? $node['nid'] : $node['entity_id'];
    $paragraphs = [];
    $paragraphField = !empty($node['field_primary_content']) ? $node['field_primary_content'] : NULL;
    $bundle = 'kci_basic_page';

    if (!empty($paragraphField)) {
      foreach ($paragraphField as $paragraph) {
        $newParagraph = Paragraph::create([
          'type' => $bundle,
          'parent_id' => $nodeId,
          'parent_type' => 'node',
        ]);
        $newParagraph->save();

        $newParagraph->field_section_title->value = $paragraph['title'];
        $newParagraph->field_section_description->value = $paragraph['description'];

        $newParagraph->save();

        $paragraphs[] = $newParagraph;
      }
    }
    return $paragraphs;
  }

}
