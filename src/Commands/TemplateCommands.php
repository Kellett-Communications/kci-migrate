<?php

declare(strict_types = 1);

namespace Drupal\kci_migrate\Commands;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\FileSystemInterface;

/**
 * Drush commands allowing to copy and make changes to template files.
 */
class TemplateCommands extends CommandBase {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The extension path resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * Constructs a TemplateCommands object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   * @param \Drupal\Core\Extension\ExtensionPathResolver $extensionPathResolver
   *   The extension path resolver.
   * @param string $appRoot
   *   The app root.
   * @param string $sitePath
   *   The site path.
   */
  public function __construct(FileSystemInterface $fileSystem, ExtensionPathResolver $extensionPathResolver, string $appRoot, string $sitePath) {
    parent::__construct($appRoot, $sitePath);
    $this->fileSystem = $fileSystem;
    $this->extensionPathResolver = $extensionPathResolver;
  }

  /**
   * Create a migration from template.
   *
   * @command kci:migrate:template
   *
   * @param string $entity_type Entity type to clone template for. Possible values: files, media, node, paragraph, paragraph, taxonomy, users.
   * @param string $source_bundle Name of the source bundle to import from. If the entity type does not use bundles, just enter the entity type again.
   * @param string $destination_bundle Optional. Name of the destination bundle to import to. If not provided, the source bundle name will be used.
   *
   * @options entity-subtype A sub-type for entity types with multiple template variants. Possible values: generic, text. Currently only for "paragraph" entity types.
   * @options source-field The name of the source field to import from. Not used by all templates.
   * @options destination-field The name of the destination field to import to. Not used by all templates.
   *
   * @aliases kmt
   *
   * @usage drush kci:migrate:template media images
   *   Generate media entity template.
   * @usage drush kci:migrate:template node basic_page page
   *   Generate a node template for migrating basic_page to page nodes.
   * @usage drush kci:migrate:template paragraph text text_long --entity-subtype=text --source-field=body --destination-field=field_text
   *   Generate a paragraph template using the paragraph_text template with source and destination fields.
   *
   * @throws \Exception
   */
  public function createTemplate(
    $entity_type = NULL,
    $source_bundle = NULL,
    $destination_bundle = NULL,
    array $options = [
      'entity-subtype' => NULL,
      'source-field' => NULL,
      'destination-field' => NULL,
    ]
    ) {

    if ($entity_type === NULL || $source_bundle === NULL) {
      throw new \Exception('Entity type and source bundle parameters are required.');
    }
    $entity_subtype = NULL;
    if (!empty($options['entity-subtype'])) {
      $entity_subtype = $options['entity-subtype'];
    }
    $this->loadConfig();
    $kciMigratePath = $this->extensionPathResolver->getPath('module', 'kci_migrate');
    $sourceFile = $kciMigratePath . '/templates/' . $entity_type . (!empty($entity_subtype) ? '_' . $entity_subtype : '') . '.yml';
    if (!file_exists($sourceFile)) {
      throw new \Exception('Could not locate template file for ' . $entity_type . (!empty($entity_subtype) ? '_' . $entity_subtype : '') . ' entity type.');
    }
    if ($destination_bundle === NULL) {
      $destination_bundle = $source_bundle;
    }
    $projectName = $this->migrateConfig['project'];
    $customModule = $this->extensionPathResolver->getPath('module', $projectName);
    if (!$customModule) {
      throw new \Exception('Could not locate migration module ' . $projectName . '.');
    }
    $migrationsDirectory = $customModule . '/migrations';
    if (!file_exists($migrationsDirectory)) {
      if (!$this->fileSystem->prepareDirectory($migrationsDirectory, FileSystemInterface::CREATE_DIRECTORY)) {
        throw new \Exception('Could not create migrations directory ' . $migrationsDirectory . '.');
      }
    }

    $destinationFile = $migrationsDirectory . '/' . $projectName . '_' . $entity_type . ($destination_bundle != $entity_type ? '_' . $destination_bundle : '') . '.yml';
    $this->fileSystem->copy($sourceFile, $destinationFile, FileSystemInterface::EXISTS_REPLACE);

    $templateFile = file_get_contents($destinationFile);
    $templateFile = str_replace('MODULE_NAME', $projectName, $templateFile);
    $templateFile = str_replace('ENTITY_TYPE', $entity_type, $templateFile);
    $templateFile = str_replace('SOURCE_BUNDLE', $source_bundle, $templateFile);
    $templateFile = str_replace('DESTINATION_BUNDLE', $destination_bundle, $templateFile);
    if (!empty($options['source-field'])) {
      $templateFile = str_replace('SOURCE_FIELD_NAME', $options['source-field'], $templateFile);
    }
    if (!empty($options['destination-field'])) {
      $templateFile = str_replace('DESTINATION_FIELD_NAME', $options['destination-field'], $templateFile);
    }
    file_put_contents($destinationFile, $templateFile);
    $this->io()->success('Created migration template ' . $destinationFile . '.');
  }

}
