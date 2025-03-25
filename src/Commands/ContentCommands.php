<?php

declare(strict_types=1);

namespace Drupal\kci_migrate\Commands;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\migrate\MigrateLookupInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Drush commands for updating/fixing content after migrations are complete.
 */
class ContentCommands extends CommandBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The migration lookup service.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  protected $migrationLookup;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The URI of the current site.
   *
   * @var string
   */
  protected $uri;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The migration bundles, keyed by entity type.
   *
   * These will be loaded from the config yml file.
   *
   * @var array
   */
  protected $migratedBundles = [];

  /**
   * The node migration IDs.
   *
   * @var array
   */
  protected $migrationIds = [];

  /**
   * The number of links updated for each field.
   *
   * @var int
   */
  protected $fieldLinkUpdateCount = 0;

  /**
   * The number of embedded images updated for each field.
   *
   * @var int
   */
  protected $fieldEmbeddedImageUpdateCount = 0;

  /**
   * The number of links updated for each entity type and bundle.
   *
   * @var int
   */
  protected $linkUpdateCountTotal = 0;

  /**
   * The host names to use for link updates.
   *
   * These will be loaded from the config yml file.
   *
   * @var array
   */
  protected $hostNames = [];

  /**
   * The language codes to use for link updates.
   *
   * This is an array of all the configured languages for the new site
   * that are not locked.
   *
   * @var array
   */
  protected $languageCodes = [];

  /**
   * Map of old to new language codes (if needed).
   *
   * These will be loaded from the config yml file.
   *
   * @var array
   */
  protected $langCodeMap = [];

  /**
   * The URLs that were not updated.
   *
   * @var array
   */
  protected $entityLinkUrlsNotUpdated = [];

  /**
   * The URLs that were not updated.
   *
   * The values in this array will be an array in this format:
   * [
   *   'entity_type'        => 'paragraph',
   *   'bundle'             => 'text',
   *   'source_entity_url'  => 'http://example.com/node/1234',
   *   'link_urls' => [
   *     'field_body' => [
   *       [
   *         'link_text' => 'Link Text',
   *         'link_url'  => 'https://www.example.com/node/1234',
   *       ],
   *     ],
   *   ],
   * ]
   *
   * @var array
   */
  protected $linkUrlsNotUpdated = [];

  /**
   * The URLs that were updated.
   *
   * The values in this array will be an array in this format:
   * [
   *   'page_url'           => 'http://example.com/node/1234',
   *   'source_type_bundle' => 'paragraph: text',
   *   'field_name'         => 'field_body',
   *   'link_text'          => 'Link Text',
   *   'old_link_url'       => 'https://www.example.com/node/1234',
   *   'new_link_url'       => 'https://www.example.com/node/5678',
   * ]
   *
   * @var array
   */
  protected $linksUpdated = [];

  /**
   * The embedded image urls that were not updated.
   *
   * @var array
   */
  protected $entityEmbeddedImagesNotUpdated = [];

  /**
   * The embedded image tags that were not updated.
   *
   * The values in this array will be an array in this format:
   * [
   *   'entity_type'        => 'paragraph',
   *   'bundle'             => 'text',
   *   'source_entity_url'  => 'http://example.com/node/1234',
   *   'image_urls' => [
   *     'field_name' => [
   *        [
   *          'image_path' => 'https://www.example.com/sites/default/files/image.jpg',
   *        ],
   *     ],
   *   ],
   * ]
   *
   * @var array
   */
  protected $embeddedImagesNotUpdated = [];

  /**
   * The embedded image tags that were updated.
   *
   * The values in this array will be an array in this format:
   * [
   *   'page_url'           => 'http://example.com/node/1234',
   *   'source_type_bundle' => 'paragraph: text',
   *   'field_name'         => 'field_body',
   *   'old_image_path'     => 'https://www.example.com/sites/default/files/image.jpg',
   *   'new_image_path'     => 'https://www.example.com/media/1234/download',
   * ]
   *
   * @var array
   */
  protected $embeddedImagesUpdated = [];

  /**
   * The parent node entities for each paragraph entity.
   *
   * We'll cache them here to avoid having to look them up
   * multiple times.
   *
   * @var array
   */
  protected $paragraphParentNodes = [];

  /**
   * Constructs a ContentCommands object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\migrate\MigrateLookupInterface $migrationLookup
   *   The migration lookup service.
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   The language manager service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param string $appRoot
   *   The app root.
   * @param string $sitePath
   *   The site path.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager, MigrateLookupInterface $migrationLookup, LanguageManager $languageManager, FileSystemInterface $fileSystem, RequestStack $requestStack, string $appRoot, string $sitePath) {
    parent::__construct($appRoot, $sitePath);
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->migrationLookup = $migrationLookup;
    $this->languageManager = $languageManager;
    $this->fileSystem = $fileSystem;
    $this->uri = $requestStack->getCurrentRequest()->getSchemeAndHttpHost();
    $this->database = Database::getConnection('default', 'migrate');
  }

  /**
   * Fix "/node/1234" links in any text fields based on migration map.
   *
   * @command kci:content:linkfix
   *
   * @aliases kcl
   */
  public function fixLinksInFields() {
    $this->loadConfig();
    $this->io()->title('Fix Links in Text and Link Fields');
    $this->io()->writeln('This command will update any links in text and link fields that link to entities to use the appropriate canonocial URI for those entities based on migration maps.');
    $this->io()->newLine();
    $this->io()->writeln('This may take a few minutes to complete.');
    $this->io()->newLine();
    $this->validateConfig();
    $fields_to_update = $this->compileFieldsToUpdate();
    if (empty($fields_to_update)) {
      $this->io()->info('No valid fields found to update. Exiting');
      return 0;
    }
    // Now loop through each field and update the values.
    $urlTotalUpdateCount = 0;
    foreach ($fields_to_update as $entity_type => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties(['type' => $bundle]);
        $entity_count = count($entities);
        $bundleLinkUpdateCount = 0;
        foreach ($entities as $entity) {
          $entityTotalUrlUpdateCount = 0;
          $this->entityLinkUrlsNotUpdated = [];
          $this->entityEmbeddedImagesNotUpdated = [];
          foreach ($fields as $field) {
            $this->fieldLinkUpdateCount = 0;
            $this->fieldEmbeddedImageUpdateCount = 0;
            $field_type = $field['field_type'];
            $field_fix_method = $this->getFieldFixMethod($field['field_type']);
            if (empty($field_fix_method) || !method_exists($this, $field_fix_method)) {
              // If we don't have a method for fixing this field type,
              // just skip it.
              $this->io()->writeln(" <bg=yellow;fg=black>[warn]</> No method found for fixing links in {$field_type} fields. Skipping.");
              continue;
            }
            $field_name = $field['field_name'];
            // Now call the method to fix the field value.
            $entityFieldUrlUpdateCount = $this->{$field_fix_method}($entity, $field_name);
            $entityTotalUrlUpdateCount += $entityFieldUrlUpdateCount;
          }
          if ($entityTotalUrlUpdateCount > 0) {
            // Only save the entity if any links were updated.
            $entity->save();
          }
          $this->compileNonUpdatedLinksData($entity_type, $entity, $bundle);
          $this->compileNonUpdatedImagesData($entity_type, $entity, $bundle);
          // Increment the link update count for the bundle so we can show it
          // in the output.
          $bundleLinkUpdateCount += $entityTotalUrlUpdateCount;
        }
        $urlTotalUpdateCount += $bundleLinkUpdateCount;
        if ($entity_count > 0) {
          $this->io()->writeln(" <bg=bright-blue;fg=black>[info]</> Updated {$bundleLinkUpdateCount} links and/or embedded images in fields for {$entity_count} {$entity_type} {$bundle} entities.");
        }
      }
    }
    if ($urlTotalUpdateCount > 0) {
      $this->io()->writeln(" <bg=green;fg=black>[success]</> Updated {$urlTotalUpdateCount} links and/or embedded images in fields for all migrated entities.");
    }
    $this->generateUpdatedLinksCsvReport();
    $this->generateUpdatedImagesCsvReport();
    $this->generateIgnoredLinksCsvReport();
    $this->generateIgnoredImagesCsvReport();
    return 0;
  }

  /**
   * Validate configuration.
   *
   * Will throw exceptions if any erros are found. Warnings
   * will be displayed for non-critical issues.
   *
   * @throws \Exception
   */
  protected function validateConfig() {
    if (empty($this->migrateConfig['migrated_bundles'])) {
      throw new \Exception('Empty or no migrated_bundles config found in the kci-migrate.yml config file. Please update it based on the example file.');
    }
    if (empty($this->migrateConfig['migration_ids'])) {
      throw new \Exception('Empty or no migration_ids config found in the kci-migrate.yml config file. Please update it based on the example file.');
    }
    if (!empty($this->migrateConfig['hostnames'])) {
      $this->hostNames = $this->migrateConfig['hostnames'];
      if (!is_array($this->hostNames)) {
        // Allow only one value to be provided.
        $this->hostNames = [$this->hostNames];
      }
    }
    if (empty($this->hostNames)) {
      $this->io()->writeln(' <bg=yellow;fg=black>[warn]</> No hostnames (domains) were provided in the config file. Any links containing hard-coded full URLs including the hostname will NOT be updated, since it will not be possible to verify whether or not they belong to the current site.');
    }
    // Get languages.
    $languages = $this->languageManager->getLanguages();
    if (!empty($languages)) {
      $this->languageCodes = array_keys($languages);
    }
    else {
      $default_language = $this->languageManager->getDefaultLanguage();
      if ($default_language) {
        $this->languageCodes = [$default_language->getId()];
      }
    }
    if (empty($this->languageCodes)) {
      $this->io()->writeln(' <bg=yellow;fg=black>[warn]</> No languages appear to be configured for the site. Any links using URL aliases containing a language code other than "en" will not be updated, since the language code will prevent successful lookup of the alias.');
    }
    $this->migratedBundles = $this->migrateConfig['migrated_bundles'];
    $this->migrationIds = $this->migrateConfig['migration_ids'];
    foreach ($this->migrationIds as $entity_type => $migration_ids) {
      if (!is_array($migration_ids)) {
        // Allow only one value to be provided.
        $this->migrationIds[$entity_type] = [$migration_ids];
      }
    }
    if (!empty($this->migrateConfig['langcode_map'])) {
      $this->langCodeMap = $this->migrateConfig['langcode_map'];
    }
  }

  /**
   * Compile the list of fields to update.
   *
   * @return array
   *   An array of fields to update.
   */
  protected function compileFieldsToUpdate(): array {
    $fields_to_update = [];
    foreach ($this->migratedBundles as $entity_type => $bundles) {
      if (empty($bundles)) {
        throw new \Exception("Empty or no {$entity_type} bundles found in the kci-migrate.yml config file. Either remove this entity type from the config file or add the bundles machine names.");
      }
      foreach ($bundles as $bundle) {
        $updatable_fields = $this->getEntityFieldsToUpdate($entity_type, $bundle);
        if (!empty($updatable_fields)) {
          $fields_to_update[$entity_type][$bundle] = $updatable_fields;
        }
      }
    }
    return $fields_to_update;
  }

  /**
   * Map field types to methods for fixing the link value.
   *
   * @param string $field_type
   *   The field type machine name.
   *
   * @return string
   *   The method name to call.
   */
  protected function getFieldFixMethod(string $field_type): string {
    $field_types_to_methods = [
      'text_with_summary' => 'updateTextField',
      'text_long'         => 'updateTextField',
      'link'              => 'updateLinkField',
    ];
    return $field_types_to_methods[$field_type] ?? '';
  }

  /**
   * Get the list of fields to update.
   *
   * @param string $entity_type
   *   The entity type to get the fields for.
   * @param string $bundle
   *   The bundle to get the fields for.
   *
   * @return array
   *   An array of fields to update.
   */
  protected function getEntityFieldsToUpdate(string $entity_type, string $bundle): array {
    $fields_to_update = [];
    // List of field types to update.
    $field_types = [
      'text_with_summary',
      'text_long',
      'link',
    ];
    $node_field_defs = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    // Loop through each field definition.
    foreach ($node_field_defs as $field_definition) {
      if (in_array($field_definition->getType(), $field_types)) {
        $fields_to_update[] = [
          'field_type'  => $field_definition->getType(),
          'field_name'  => $field_definition->getName(),
          'field_label' => $field_definition->getLabel(),
        ];
      }
    }
    return $fields_to_update;
  }

  /**
   * Update links in a text field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to update.
   * @param string $field_name
   *   The field name to update.
   *
   * @return int
   *   The number of links updated.
   */
  protected function updateTextField(FieldableEntityInterface $entity, string $field_name): int {
    $entityTotalUrlUpdateCount = 0;
    $field_value = $entity->get($field_name)->getValue();
    if (!empty($field_value) && is_array($field_value)) {
      $this->fieldLinkUpdateCount = 0;
      foreach ($field_value as $delta => $value) {
        // First update any embedded image tags.
        $new_value = $this->updateHtmlImgTags($value['value'], $field_name, $entity);
        // Then updated any links.
        $new_value = $this->updateHtmlLinks($new_value, $field_name, $entity);
        // Now update the field value.
        $field_value[$delta]['value'] = $new_value;
      }
      if ($this->fieldLinkUpdateCount > 0) {
        // Only set the value if any links were updated.
        $entityTotalUrlUpdateCount += $this->fieldLinkUpdateCount + $this->fieldEmbeddedImageUpdateCount;
        $entity->set($field_name, $field_value);
      }
    }
    return $entityTotalUrlUpdateCount;
  }

  /**
   * Update links in a link field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to update.
   * @param string $field_name
   *   The field name to update.
   *
   * @return int
   *   The number of links updated.
   */
  protected function updateLinkField(FieldableEntityInterface $entity, string $field_name): int {
    $entityLinkUpdateCount = 0;
    // Get all the values of the link field.
    $field_value = $entity->get($field_name)->getValue();
    if (!empty($field_value) && is_array($field_value)) {
      $this->fieldLinkUpdateCount = 0;
      foreach ($field_value as $delta => $value) {
        $uri = $value['uri'];
        if (stristr($uri, 'internal:') !== FALSE || stristr($uri, 'entity:') !== FALSE) {
          // This is an internal or entity link, so we can try to update it.
          // First let's get the path.
          $uri_parts = explode(':', $uri);
          $link_data = $this->newEntityLinkData($uri_parts[1]);
          if ($link_data === FALSE) {
            // If we don't have a matching link URL, just skip this link.
            if (empty($this->entityLinkUrlsNotUpdated[$field_name])) {
              $this->entityLinkUrlsNotUpdated[$field_name] = [];
            }
            $this->entityLinkUrlsNotUpdated[$field_name][] = [
              'link_text' => $value['title'],
              'link_url'  => $uri_parts[1],
            ];
            continue;
          }
          $field_value[$delta]['uri'] = 'entity:' . $link_data['link_path'];
          if ($link_data['link_new_window']) {
            $field_value[$delta]['options']['attributes']['target'] = '_blank';
          }
          $node_entity = $this->getParentNodeEntity($entity);
          if (!($node_entity instanceof EntityInterface)) {
            // If we don't have a node entity, continue. This can
            // happen in case of an orphaned paragraph entity.
            continue;
          }
          $this->fieldLinkUpdateCount += 1;
          $this->linksUpdated[] = [
            'page_url'           => $this->uri . $node_entity->toUrl()->toString(),
            'source_type_bundle' => $entity->getEntityTypeId() . ': ' . $entity->bundle(),
            'field_name'         => $field_name,
            'link_text'          => $value['title'],
            'old_link_url'       => $uri_parts[1],
            'new_link_url'       => $link_data['link_path'],
          ];
        }
      }
      if ($this->fieldLinkUpdateCount > 0) {
        // Only set the value if any links were updated.
        $entityLinkUpdateCount += $this->fieldLinkUpdateCount;
        $entity->set($field_name, $field_value);
      }
    }
    return $entityLinkUpdateCount;
  }

  /**
   * Update links in a string.
   *
   * @param string $value
   *   The string to update.
   * @param string $field_name
   *   The field name being updated.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being updated.
   *
   * @return string
   *   The updated string.
   */
  protected function updateHtmlLinks(string $value, string $field_name, FieldableEntityInterface $entity): string {
    // First let's trim the string of any excess whitespace.
    $value = trim($value);
    if (!empty($value) && strpos($value, '<a') !== FALSE) {
      // Ensure we have a non-empty value that contains at least one anchor tag.
      // Parse the value as html and then turn it into a DOMXPath object.
      $dom = Html::load($value);
      $xpath = new \DOMXPath($dom);

      // Now we can tranverse the DOM and update the links.
      foreach ($xpath->query('//a[@href]') as $element) {
        /**
         * @var \DOMElement $element
         */
        $href = $element->getAttribute('href');
        $link_data = $this->newEntityLinkData($href);
        if ($link_data === FALSE) {
          // If we don't have a matching link URL, just skip this link.
          if (empty($this->entityLinkUrlsNotUpdated[$field_name])) {
            $this->entityLinkUrlsNotUpdated[$field_name] = [];
          }
          $this->entityLinkUrlsNotUpdated[$field_name][] = [
            'link_text' => $element->nodeValue,
            'link_url'  => $href,
          ];
          continue;
        }
        // Now update the href attribute.
        $element->setAttribute('href', $link_data['link_path']);
        if ($link_data['link_new_window']) {
          $element->setAttribute('target', '_blank');
        }
        // If the link is a media entity, we need to add data attributes
        // for the Linkit media entity download plugin to work.
        if (!empty($link_data['link_entity_uuid'])) {
          $element->setAttribute('data-entity-type', $link_data['link_entity_type']);
          $element->setAttribute('data-entity-uuid', $link_data['link_entity_uuid']);
          $element->setAttribute('data-entity-substitution', $link_data['link_entity_substitution']);
        }
        $this->fieldLinkUpdateCount += 1;
        $node_entity = $this->getParentNodeEntity($entity);
        if (!($node_entity instanceof EntityInterface)) {
          // If we don't have a node entity, continue. This can
          // happen in case of an orphaned paragraph entity.
          continue;
        }
        // Get the element text content.
        $this->linksUpdated[] = [
          'page_url'           => $this->uri . $node_entity->toUrl()->toString(),
          'source_type_bundle' => $entity->getEntityTypeId() . ': ' . $entity->bundle(),
          'field_name'         => $field_name,
          'link_text'          => $element->nodeValue,
          'old_link_url'       => $href,
          'new_link_url'       => $link_data['link_path'],
        ];
      }
      // Now we can re-serialize the DOM back into a string.
      $value = Html::serialize($dom);
    }
    return $value;

  }

  /**
   * Replace image tags with drupal media tags in a string.
   *
   * @param string $value
   *   The string to update.
   * @param string $field_name
   *   The field name being updated.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being updated.
   *
   * @return string
   *   The updated string.
   */
  protected function updateHtmlImgTags(string $value, string $field_name, EntityInterface $entity): string {
    // First let's trim the string of any excess whitespace.
    $value = trim($value);
    if (!empty($value) && strpos($value, '<a') !== FALSE) {
      // Ensure we have a non-empty value that contains at least on anchor tag.
      // Parse the value as html and then turn it into a DOMXPath object.
      $dom = Html::load($value);
      $xpath = new \DOMXPath($dom);

      // Now we can tranverse the DOM and update the links.
      foreach ($xpath->query('//img[@src]') as $element) {
        /**
         * @var \DOMElement $element
         */
        $image_path = $element->getAttribute('src');
        $link_data = $this->newEntityLinkData($image_path);
        if ($link_data === FALSE) {
          // If we don't have a matching link URL, just skip this link.
          if (empty($this->entityEmbeddedImagesNotUpdated[$field_name])) {
            $this->entityEmbeddedImagesNotUpdated[$field_name] = [];
          }
          $this->entityEmbeddedImagesNotUpdated[$field_name][] = [
            'image_path'  => $image_path,
          ];
          continue;
        }
        // Now we need to replace the img element with a new element
        // using the drupal-media tag.
        $newNode = $dom->createElement('drupal-media', '&nbsp;');
        $newNode->setAttribute('data-entity-type', 'media');
        $newNode->setAttribute('data-entity-uuid', $link_data['link_entity_uuid']);
        if (!empty($link_data['link_image_style']) && !empty($this->migrateConfig['image_styles_to_media_view_modes'][$link_data['link_image_style']])) {
          $newNode->setAttribute('data-view-mode', $this->migrateConfig['image_styles_to_media_view_modes'][$link_data['link_image_style']]);
        }
        $element->parentNode->replaceChild($newNode, $element);
        $this->fieldEmbeddedImageUpdateCount += 1;
        $node_entity = $this->getParentNodeEntity($entity);
        if (!($node_entity instanceof EntityInterface)) {
          // If we don't have a node entity, continue. This can
          // happen in case of an orphaned paragraph entity.
          continue;
        }
        // Get the element text content.
        $this->embeddedImagesUpdated[] = [
          'page_url'           => $this->uri . $node_entity->toUrl()->toString(),
          'source_type_bundle' => $entity->getEntityTypeId() . ': ' . $entity->bundle(),
          'field_name'         => $field_name,
          'old_image_path'     => $image_path,
          'new_image_path'     => $link_data['link_path'],
        ];
      }
      // Now we can re-serialize the DOM back into a string.
      $value = Html::serialize($dom);
    }
    return $value;

  }

  /**
   * Get the new entity path from the old entity path.
   *
   * This parses out the path matching for a valid entity path
   * and uses the appropriate migration lookup to get the new
   * entity ID. It then returns the new entity path, or FALSE
   * if a new path could not be found.
   *
   * @todo Expand this to handle other entity types.
   *
   * @param string $old_entity_path
   *   The old entity path.
   *
   * @return array|bool
   *   An array of data for the new entity link, or FALSE.
   */
  protected function newEntityLinkData(string $old_entity_path): array|bool {
    $parsed_url = parse_url($old_entity_path);
    if ($parsed_url === FALSE) {
      // If we can't parse the URL, just skip this link.
      return FALSE;
    }
    $old_entity_data = $this->entityDataFromParsedUrl($parsed_url);
    if ($old_entity_data === FALSE) {
      // If we don't have a matching link URL, just skip this link.
      return FALSE;
    }
    // Map of old to new entity types:
    $old_to_new_entity_base_paths = [
      'node' => 'node',
      'file' => 'media',
    ];
    $new_entity_data = [
      'entity_type'         => $old_to_new_entity_base_paths[$old_entity_data['entity_type']],
      'entity_id'           => NULL,
      'entity_uuid'         => NULL,
      'entity_substitution' => 'canonical',
      'new_window'          => FALSE,
      // This would only apply to media entities that are images.
      'image_style'  => '',
    ];
    if ($old_entity_data['entity_type'] == 'node' && $old_entity_data['entity_id'] === -1) {
      // A returned node ID of -1 denotes the home page, so we don't
      // need to look up the new node ID.
      $new_entity_data['entity_id'] = -1;
    }
    else {
      // This is NOT the home page, so we need to look up the new entity ID.
      // We'll do that using the migration lookup service.
      $use_migration_ids = $this->migrationIds[$new_entity_data['entity_type']] ?? [];
      if (empty($use_migration_ids)) {
        // If we don't have any migration IDs for this entity type, just skip
        // this link.
        return FALSE;
      }
      $new_entity_ids = [];
      try {
        foreach ($use_migration_ids as $migration_id) {
          $new_entity_ids = $this->migrationLookup->lookup($migration_id, [$old_entity_data['entity_id']]);
          if (!empty($new_entity_ids)) {
            break;
          }
        }
      }
      catch (\Exception $e) {
        $this->io()->writeln(" <bg=red;fg=black>[error]</> Failure looking up {$new_entity_data['entity_type']} entity with {$old_entity_data['entity_type']} ID {$old_entity_data['entity_id']} in {$migration_id}: {$e->getMessage()}. Skipping.");
        return FALSE;
      }
      if (empty($new_entity_ids)) {
        // No new node IDs were matched, so skip this link.
        return FALSE;
      }
      /* The migration lookup returns an array of arrays like this:
       * Array
       * (
       *     [0] => Array
       *         (
       *             [entity_id_property_name] => 1234
       *         )
       * )
       */
      // We only need the entity ID, so get the first value of the first array.
      $new_entity_data['entity_id'] = (int) reset($new_entity_ids[0]);
      // Load the new entity.
      $new_entity = $this->entityTypeManager->getStorage($new_entity_data['entity_type'])->load($new_entity_data['entity_id']);
      if ($new_entity === NULL) {
        return FALSE;
      }
      // Set the new entity UUID.
      $new_entity_data['entity_uuid'] = $new_entity->uuid();
      if ($new_entity_data['entity_type'] == 'media') {
        // If the entity is a media entity, we want to use
        // media_download_inline entity substitution.
        $new_entity_data['entity_substitution'] = 'media_download_inline';
        // Have media links open in a new window.
        $new_entity_data['new_window'] = TRUE;
      }
    }
    if ($new_entity_data['entity_type'] == 'node' && $new_entity_data['entity_id'] === -1) {
      // If the new node ID is -1, that denotes the home page.
      // In that case make the new href just "/".
      $new_path = '/';
    }
    else {
      // Otherwise the new href value needs to be /node/1234.
      $new_path = "/" . $new_entity_data['entity_type'] . "/{$new_entity_data['entity_id']}";
    }
    // Next append any query string and/or fragment.
    $new_path .= !empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $new_path .= !empty($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
    return [
      'link_path'                => $new_path,
      'link_entity_type'         => $new_entity_data['entity_type'],
      'link_entity_uuid'         => $new_entity_data['entity_uuid'],
      'link_entity_substitution' => $new_entity_data['entity_substitution'],
      'link_new_window'          => $new_entity_data['new_window'],
      'link_image_style'         => $new_entity_data['image_style'],
    ];
  }

  /**
   * Compile the data of links that were not updated.
   *
   * This is used for the final CSV report at the end.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $bundle
   *   The bundle.
   */
  protected function compileNonUpdatedLinksData(string $entity_type, EntityInterface $entity, string $bundle): void {
    if (!empty($this->entityLinkUrlsNotUpdated)) {
      // Compose the data about the entity and the links that were not
      // updated.
      // First check to see if the entity is a paragraph. If so,
      // we need to get the parent entity.
      $node_entity = $this->getParentNodeEntity($entity);
      if (!($node_entity instanceof EntityInterface)) {
        // If there's no parent entity, just skip this one.
        // This would happen in case of an orphaned paragraph
        // entity.
        return;
      }
      $entity_link_data = [
        'entity_type'        => $entity_type,
        'bundle'             => $bundle,
        'source_entity_url'  => $this->uri . $node_entity->toUrl()->toString(),
        'link_urls'          => $this->entityLinkUrlsNotUpdated,
      ];
      // Add the URLs that were not updated to the list.
      $this->linkUrlsNotUpdated = array_merge($this->linkUrlsNotUpdated, [$entity_link_data]);
    }
  }

  /**
   * Compile the data of embedded images that were not updated.
   *
   * This is used for the final CSV report at the end.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $bundle
   *   The bundle.
   */
  protected function compileNonUpdatedImagesData(string $entity_type, EntityInterface $entity, string $bundle): void {
    if (!empty($this->entityEmbeddedImagesNotUpdated)) {
      // Compose the data about the entity and the links that were not
      // updated.
      // First check to see if the entity is a paragraph. If so,
      // we need to get the parent entity.
      $node_entity = $this->getParentNodeEntity($entity);
      if (!($node_entity instanceof EntityInterface)) {
        // If there's no parent entity, just skip this one.
        // This would happen in case of an orphaned paragraph
        // entity.
        return;
      }
      $embedded_image_data = [
        'entity_type'        => $entity_type,
        'bundle'             => $bundle,
        'source_entity_url'  => $this->uri . $node_entity->toUrl()->toString(),
        'image_urls'         => $this->entityEmbeddedImagesNotUpdated,
      ];
      // Add the URLs that were not updated to the list.
      $this->embeddedImagesNotUpdated = array_merge($this->embeddedImagesNotUpdated, [$embedded_image_data]);
    }
  }

  /**
   * Get the parent node entity for an entity.
   *
   * This is really just for paragraph entities, but you can
   * pass any entity type in.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to find the parent of.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The appropriate node entity, or NULL if not found.
   */
  protected function getParentNodeEntity(FieldableEntityInterface $entity): ?EntityInterface {
    if ($entity->getEntityTypeId() === 'paragraph') {
      if (!isset($this->paragraphParentNodes[$entity->id()])) {
        /**
         * @var \Drupal\paragraphs\ParagraphInterface $entity
         */
        $parent_entity = $entity->getParentEntity();
        while (($parent_entity instanceof EntityInterface) && $parent_entity->getEntityTypeId() !== 'node') {
          /**
           * @var \Drupal\paragraphs\ParagraphInterface $parent_entity
           */
          $parent_entity = $parent_entity->getParentEntity();
        }
        $this->paragraphParentNodes[$entity->id()] = $parent_entity;
      }
      return $this->paragraphParentNodes[$entity->id()];
    }
    return $entity;
  }

  /**
   * Generate a CSV report of the URLs that were not updated.
   */
  protected function generateIgnoredLinksCsvReport(): void {
    if (!empty($this->linkUrlsNotUpdated)) {
      $csv_rows = [];
      $ignored_link_count = 0;
      foreach ($this->linkUrlsNotUpdated as $entity_link_data) {
        foreach ($entity_link_data['link_urls'] as $field_name => $urls) {
          foreach ($urls as $link_data) {
            $url_type = $this->getUrlTypeForReport($link_data['link_url']);
            // Group the CSV data by URL type.
            if (empty($csv_rows[$url_type])) {
              $csv_rows[$url_type] = [];
            }
            $csv_rows[$url_type][] = [
              $entity_link_data['source_entity_url'],
              $entity_link_data['entity_type'] . ': ' . $entity_link_data['bundle'],
              $field_name,
              $url_type,
              $link_data['link_text'],
              $link_data['link_url'],
            ];
            $ignored_link_count += 1;
          }
        }
      }
      // Sort in reverse order by key so that the URL type groups
      // are in the correct order.
      krsort($csv_rows);
      // Now flatten the array.
      $csv_rows = array_merge(...array_values($csv_rows));
      // Then prepend it with the header row.
      array_unshift($csv_rows, [
        'Source Page',
        'Source Entity: Bundle',
        'Field Name',
        'Link Type',
        'Link Text',
        'Link URL',
      ]);
      $csv_filename = 'link-updates-ignored-' . date('Y-m-d-H-i-s') . '.csv';
      $csv_file_path = $this->generateCsvFile($csv_rows, $csv_filename);
      $this->io()->writeln(" <bg=bright-blue;fg=black>[info]</> A CSV report has been generated for {$ignored_link_count} URLs that could not be automatically updated. The file hase been saved to {$csv_file_path}.");
    }
  }

  /**
   * Generate a CSV report of the URLs that were not updated.
   */
  protected function generateIgnoredImagesCsvReport(): void {
    if (!empty($this->embeddedImagesNotUpdated)) {
      $csv_rows = [];
      $ignored_image_count = 0;
      foreach ($this->embeddedImagesNotUpdated as $entity_image_data) {
        foreach ($entity_image_data['image_urls'] as $field_name => $urls) {
          foreach ($urls as $image_data) {
            $url_type = $this->getUrlTypeForReport($image_data['image_path']);
            // Group the CSV data by URL type.
            if (empty($csv_rows[$url_type])) {
              $csv_rows[$url_type] = [];
            }
            $csv_rows[$url_type][] = [
              $entity_image_data['source_entity_url'],
              $entity_image_data['entity_type'] . ': ' . $entity_image_data['bundle'],
              $field_name,
              $url_type,
              $image_data['image_path'],
            ];
            $ignored_image_count += 1;
          }
        }
      }
      // Sort in reverse order by key so that the URL type groups
      // are in the correct order.
      krsort($csv_rows);
      // Now flatten the array.
      $csv_rows = array_merge(...array_values($csv_rows));
      // Then prepend it with the header row.
      array_unshift($csv_rows, [
        'Source Page',
        'Source Entity: Bundle',
        'Field Name',
        'Image URL Type',
        'Image Path',
      ]);
      $csv_filename = 'image-updates-ignored-' . date('Y-m-d-H-i-s') . '.csv';
      $csv_file_path = $this->generateCsvFile($csv_rows, $csv_filename);
      $this->io()->writeln(" <bg=bright-blue;fg=black>[info]</> A CSV report has been generated for {$ignored_image_count} embedded images that could not be automatically updated. The file hase been saved to {$csv_file_path}.");
    }
  }

  /**
   * Generate a CSV report of the URLs that were updated.
   */
  protected function generateUpdatedLinksCsvReport(): void {
    if (!empty($this->linksUpdated)) {
      // Store the URLs that were updated in a CSV file in public files.
      $csv_rows = [
        [
          'Page URL',
          'Source Entity: Bundle',
          'Field Name',
          'Link Text',
          'Old Link URL',
          'New Link URL',
        ],
      ];
      foreach ($this->linksUpdated as $link_data) {
        $csv_rows[] = [
          $link_data['page_url'],
          $link_data['source_type_bundle'],
          $link_data['field_name'],
          $link_data['link_text'],
          $link_data['old_link_url'],
          $link_data['new_link_url'],
        ];
      }
      $csv_filename = 'link-updates-complete-' . date('Y-m-d-H-i-s') . '.csv';
      $csv_file_path = $this->generateCsvFile($csv_rows, $csv_filename);
      $link_update_count = count($this->linksUpdated);
      $this->io()->writeln(" <bg=green;fg=black>[success]</> A CSV report has been generated for {$link_update_count} URLs that were updated. The file hase been saved to {$csv_file_path}.");
    }
  }

  /**
   * Generate a CSV report of the embedded images that were updated.
   */
  protected function generateUpdatedImagesCsvReport(): void {
    if (!empty($this->embeddedImagesUpdated)) {
      // Store the URLs that were updated in a CSV file in public files.
      $csv_rows = [
        [
          'Page URL',
          'Source Entity: Bundle',
          'Field Name',
          'Old Image Path',
          'New Image Path',
        ],
      ];
      foreach ($this->embeddedImagesUpdated as $image_data) {
        $csv_rows[] = [
          $image_data['page_url'],
          $image_data['source_type_bundle'],
          $image_data['field_name'],
          $image_data['old_image_path'],
          $image_data['new_image_path'],
        ];
      }
      $csv_filename = 'image-updates-complete-' . date('Y-m-d-H-i-s') . '.csv';
      $csv_file_path = $this->generateCsvFile($csv_rows, $csv_filename);
      $image_update_count = count($this->embeddedImagesUpdated);
      $this->io()->writeln(" <bg=green;fg=black>[success]</> A CSV report has been generated for {$image_update_count} embedded images that were updated. The file hase been saved to {$csv_file_path}.");
    }
  }

  /**
   * Generate a CSV file.
   *
   * @param array $csv_rows
   *   The rows to add to the CSV file.
   * @param string $csv_filename
   *   The filename to use.
   *
   * @return string
   *   The full path to the CSV file.
   */
  protected function generateCsvFile(array $csv_rows, string $csv_filename): string {
    $csv_file_path = 'public://' . $csv_filename;
    $fh = fopen('php://memory', 'r+');
    foreach ($csv_rows as $row) {
      fputcsv($fh, $row);
    }
    rewind($fh);
    $csv_data = stream_get_contents($fh);
    // Write the CSV file.
    $this->fileSystem->saveData($csv_data, $csv_file_path, FileSystemInterface::EXISTS_REPLACE);
    // Get the full path to the CSV file.
    return $this->fileSystem->realpath($csv_file_path);
  }

  /**
   * Get the URL type for the report.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return string
   *   The URL type.
   */
  protected function getUrlTypeForReport(string $url): string {
    $parsed_url = parse_url($url);
    if (!empty($parsed_url['host'])) {
      // If there's a host, it's an external URL.
      return 'External';
    }
    // If there's no host, then it's either a mailto link, an internal path,
    // or a hash link (has a fragment).
    if (stristr($url, 'mailto:') !== FALSE) {
      return 'Email';
    }
    if (!empty($parsed_url['fragment'])) {
      return 'Anchor';
    }
    // If we get here, it's an internal path.
    return 'Internal';
  }

  /**
   * Parse the link URL.
   *
   * @param array $parsed_url
   *   The parse URL array (result of parse_url()).
   *
   * @return array|false
   *   An array with entity_type and entity_id, or FALSE.
   */
  protected function entityDataFromParsedUrl(array $parsed_url): array|bool {
    if (!empty($parsed_url['host'])) {
      // If the URL has a host, we can only update the link
      // if the host matches one of the host names in the config.
      // If hostnames are configured and it has a host that doesn't match,
      // then it's an external URL and shouldn't be updated anyway.
      if (empty($this->hostNames) || !in_array($parsed_url['host'], $this->hostNames)) {
        return FALSE;
      }
    }
    // We either have a matching host, or the link is relative.
    // Start by just looking at the path to see if it matches
    // node/1234.
    $path = $parsed_url['path'] ?? '';
    // Trimming shouldn't be needed, but just to make sure.
    $path = trim($path, '/');
    // One thing we should account for are the odd cases where a link has the
    // domain in it for the URL, but did not include the scheme. For example,
    // www.example.com or www.example.com/node/1234. In this case, the
    // parse_url() function will not include a host and instead will have the
    // host as part of the path element. As such, we should check to see if one
    // of the host names is in $parsed_url['path'] and if so, remove it.
    if (!empty($this->hostNames)) {
      foreach ($this->hostNames as $host_name) {
        if (stristr($path, $host_name) !== FALSE) {
          // We have a match, so remove it from the path.
          $path = str_replace($host_name, '', $path);
          // Trim again.
          $path = trim($path, '/');
          break;
        }
      }
    }
    if (!empty($path)) {
      // If the path has a language code, remove it.
      $path = $this->pathWithoutLangcode($path);
    }
    if (empty($path) && empty($parsed_url['fragment'])) {
      // Link has no path or fragment, which means it's going to the home page.
      // In this case we don't actually need to look up the node ID,
      // nor does the link really need to be updated. We return an entity_type
      // of node and an entity_id of -1 to account for this special case.
      return [
        'entity_type' => 'node',
        'entity_id'   => -1,
      ];
    }
    // See if it's a mailto: link and, if so, ignore it.
    if (stristr($path, 'mailto:') !== FALSE) {
      return FALSE;
    }
    // If we have a path, see if it matches [entity_type]/1234, for example
    // node/1234 or file/1234. If it does, we're all set.
    $result = $this->entityPathMatch($path);
    if (is_array($result)) {
      return $result;
    }
    // No match. This means it could either be a direct file path, such as
    // sites/default/files/foldername/file.pdf, or it could be an alias.
    // Let's first try the direct file path method, since it will very quickly
    // return FALSE if it's not a direct file path.
    $result = $this->directFilePathMatch($path);
    if (is_array($result)) {
      return $result;
    }

    // No match for a direct file path, so let's assume it's an alias and try
    // to get the canonical path based on that.
    $result = $this->aliasPathMatch($path);
    if (is_array($result)) {
      return $result;
    }

    // No match for an alias, so let's try a redirect.
    $result = $this->redirectPathMatch($path);
    // Nothing else to check, so just return the result.
    return $result;
  }

  /**
   * Get the entity type and ID from a path.
   *
   * @param string $path
   *   The path to parse.
   *
   * @return array|bool
   *   An array with entity_type and entity_id, or FALSE if no match.
   */
  protected function entityPathMatch(string $path): array|bool {
    // Possible old entity types:
    $old_entity_base_paths = [
      'node',
      'file',
    ];
    $matches = [];
    if (preg_match('/(' . implode('|', $old_entity_base_paths) . ')\/(\d+)/', $path, $matches)) {
      return [
        'entity_type' => $matches[1],
        'entity_id'   => (int) $matches[2],
      ];
    }
    return FALSE;
  }

  /**
   * Try to find the canonical path from an aliased path.
   *
   * @param string $path
   *   The path to parse.
   *
   * @return array|bool
   *   The result of $this->entityPathMatch().
   */
  protected function aliasPathMatch(string $path): array|bool {
    // First, check to see if the path is a direct file path. If so, we can
    // skip the rest of this.
    if ($this->pathIsDirectFilePath($path)) {
      return FALSE;
    }
    // Try to look up the alias in the migrate database.
    $query = $this->database->select('url_alias', 'ua')
      ->fields('ua', ['source'])
      ->condition('alias', "{$path}");
    // Log the database query and bindings.
    $source_path = $query->execute()->fetchField();
    if (!empty($source_path)) {
      $source_path = trim($source_path, '/');
      // We have a matching source path, so return it.
      return $this->entityPathMatch($source_path);
    }
    return $this->entityPathMatch($path);
  }

  /**
   * Try to find the canonical path from a redirect path.
   *
   * @param string $path
   *   The path to parse.
   *
   * @return array|bool
   *   The result of $this->entityPathMatch().
   */
  protected function redirectPathMatch(string $path): array|bool {
    // First, check to see if the path is a direct file path. If so, we can
    // skip the rest of this.
    if ($this->pathIsDirectFilePath($path)) {
      return FALSE;
    }

    // Try to look up the alias in the migrate database.
    try {
      $query = $this->database->select('redirect', 'r')
        ->fields('r', ['redirect'])
        ->condition('source', "{$path}");
      // Log the database query and bindings.
      $redirect_path = $query->execute()->fetchField();
      if (!empty($redirect_path)) {
        $redirect_path = trim($redirect_path, '/');
        // We have a matching source path, so return it.
        return $this->entityPathMatch($redirect_path);
      }
    }
    catch (\Exception $e) {
      // If the redirect table does not exist, then we can't look up
      // the redirect path, so just skip it.
    }

    return $this->entityPathMatch($path);
  }

  /**
   * Get the entity type and ID from a direct file path.
   *
   * If the path matches 'sites/[default_or_sitename]/files/[file_path]',
   * we can try to look up the file entity ID directly in the migrate database.
   * If we find a matching file entity ID, we can then look up the media entity
   * ID using the migration lookup service.
   *
   * @param string $path
   *   The path to parse.
   *
   * @return array|bool
   *   An array with entity_type and entity_id, or FALSE if no match.
   */
  protected function directFilePathMatch(string $path): array|bool {
    // First, make sure we have media migrations.
    $migration_ids = $this->migrationIds['media'] ?? [];
    if (empty($migration_ids)) {
      // If we don't have any media migration IDs, we have to skip it.
      return FALSE;
    }
    // Match for sites/[default_or_sitename]/files/[file_path].
    if ($file_path = $this->pathIsDirectFilePath($path)) {
      // The resulting file path will be something like
      // 'foldername/filename.pdf'. If the file is an image, however,
      // it could include an image styel in the path, for example
      // 'styles/large/public/foldername/filename.jpg'.
      // Furthermore, the file path could be in the public or private
      // file system. So we need to strip out the 'styles/[style]/public'
      // or 'styles/[style]/private' part of the path. However in that case
      // it's also possible that the filename for the image style is different
      // than the original file name, so we need to check for that as well.
      $file_path_parts = explode('/', $file_path);
      $image_style = '';
      if (count($file_path_parts) > 1 && $file_path_parts[0] == 'styles') {
        // We have an image style in the path, so we need to remove it.
        // First check to see if the file path matches the pattern
        // 'styles/[style]/public/[file_path]' or
        // 'styles/[style]/private/[file_path]'.
        $file_path = implode('/', array_slice($file_path_parts, 2));
        $image_style = $file_path_parts[1];
        // Now check to see if the file exists in the public or private
        // file system.
        $public_file_path = 'public://' . $file_path;
        $private_file_path = 'private://' . $file_path;
        if ($this->fileSystem->realpath($public_file_path) === FALSE && $this->fileSystem->realpath($private_file_path) === FALSE) {
          // If the file does not exist in either the public or private
          // file system, then it may just be that the original filname was
          // converted to lowercase with spaces and other characters converted
          // to underscores. So let's try converting the filename and testing
          // again.
          $file_path_parts = explode('/', $file_path);
          $filename = end($file_path_parts);
          $filename = strtolower($filename);
          $filename = preg_replace('/[^a-z0-9_\-\.\/]/', '_', $filename);
          if (count($file_path_parts) > 1) {
            $file_path = implode('/', array_slice($file_path_parts, 0, -1)) . '/' . $filename;
          }
          else {
            $file_path = $filename;
          }
          $public_file_path = 'public://' . $file_path;
          $private_file_path = 'private://' . $file_path;
          if ($this->fileSystem->realpath($public_file_path) === FALSE && $this->fileSystem->realpath($private_file_path) === FALSE) {
            // If the file still does not exist in either the public or private
            // file system, then we have to skip it.
            return FALSE;
          }
        }
      }
      // We have a match, so let's try to load the file
      // by filename from the migrate database.
      // First do a direct DB query against the migrate database
      // to see if we can find the file.
      $query = $this->database->select('file_managed', 'fm')
        ->fields('fm', ['fid']);
      $query->condition($query->orConditionGroup()
        ->condition('fm.uri', 'public://' . $file_path)
        ->condition('fm.uri', 'private://' . $file_path)
      );
      $file_id = $query->execute()->fetchField();
      if (empty($file_id)) {
        // No file ID found, so skip it.
        return FALSE;
      }
      // We have a file ID, so return the expected array with the new entity
      // type and the old entity ID.
      return [
        'entity_type' => 'file',
        'entity_id'   => (int) $file_id,
        // Include the image style so we can convert it, if possible.
        'image_style' => $image_style,
      ];
    }
    // No match.
    return FALSE;
  }

  /**
   * Check if a path is a direct file path.
   *
   * @param string $path
   *   The path to check.
   *
   * @return string|bool
   *   The file path, if it is a direct file path, otherwise FALSE.
   */
  protected function pathIsDirectFilePath(string $path): string|bool {
    $matches = [];
    if (preg_match('/sites\/[^\/]+\/files\/(.*)/i', $path, $matches)) {
      return $matches[1];
    }
    return FALSE;
  }

  /**
   * Get the path without the language code.
   *
   * For this to work properly, the new site must have all the same
   * languages configured as the old D7 site, and if any language codes are
   * different between the two sites, a langcode_map must be provided in the
   * kci-migrate.yml config file. If the path does not contain a language code
   * that can be matched, the path will be returned unchanged.
   *
   * @param string $path
   *   The path to remove the language code from.
   *
   * @return string
   *   The path without the language code, if present and can be matched.
   *   Otherwise the original path is returned.
   */
  protected function pathWithoutLangcode(string $path): string {
    if (!empty($this->languageCodes)) {
      // If we have language codes, we can check if the path starts with
      // one of them. If we have no language codes then the site is probably
      // not multilingual, so this step will be skipped.
      $old_language_code = '';
      $language_match = FALSE;
      foreach ($this->languageCodes as $new_language_code) {
        // Check to see if the path starts with the language code.
        // If there's a langcode map, use that to check for the old
        // language code.
        if (!empty($this->langCodeMap) && !empty($this->langCodeMap[$new_language_code])) {
          // The new language code has a matching old language code,
          // so use that.
          $old_language_code = $this->langCodeMap[$new_language_code];
        }
        else {
          // No matching old language code, so just use the new one.
          $old_language_code = $new_language_code;
        }
        // Now check if the path starts with the language code.
        if (strpos($path, "{$old_language_code}/") === 0) {
          // We have a match, so we can stop checking.
          $language_match = TRUE;
          break;
        }
      }
      if (!$language_match) {
        // No match, so let's check for 'en' as a default fallback.
        if (strpos($path, "en/") === 0) {
          $language_match = TRUE;
          $old_language_code = 'en';
          $new_language_code = 'en';
        }
      }
      if ($language_match) {
        // We have a language match, so remove the language code from the path.
        $path = substr($path, strlen($old_language_code) + 1);
      }
    }
    return $path;
  }

}
