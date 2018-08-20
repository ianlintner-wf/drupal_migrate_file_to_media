<?php

namespace Drupal\migrate_file_to_media\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drush\Commands\DrushCommands;

/**
 * Drush 9 commands for migrate_file_to_media.
 */
class MediaMigrateCommands extends DrushCommands {

  /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager */
  private $entity_field_manager;

  /** @var EntityTypeManagerInterface $entity_storage_manager */
  private $entity_type_manager;

  /** @var \Drupal\Core\Database\Connection */
  private $connection;

  /**
   * MediaMigrateCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(
    EntityFieldManagerInterface $entityFieldManager,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection
  ) {
    $this->entity_field_manager = $entityFieldManager;
    $this->entity_type_manager = $entity_type_manager;
    $this->connection = $connection;
  }

  /**
   * Create create media destination fields.
   *
   * @command migrate:file-media-fields
   * @aliases mf2m
   *
   * @param $entity_type
   * @param $bundle
   * @param $source_field_type
   * @param $target_media_bundle
   *
   *
   */
  public function migrateFileFields($entity_type, $bundle, $source_field_type, $target_media_bundle) {

    $this->output()
      ->writeln("Creating media reference fields for {$entity_type} : {$bundle}.");

    $bundle_fields = $this->entity_field_manager->getFieldDefinitions($entity_type, $bundle);

    // Gather a list of all target fields.
    $map = \Drupal::entityManager()->getFieldMapByFieldType($source_field_type);
    $source_fields = [];
    foreach ($map[$entity_type] as $name => $data) {
      foreach ($data['bundles'] as $bundle_name) {
        if ($bundle_name == $bundle) {
          $target_field_name = $name . '_media';
          $source_fields[$target_field_name] = $bundle_fields[$name];
          $this->output()->writeln('Found field: ' . $name);
        }
      }
    }

    $map = \Drupal::entityManager()->getFieldMapByFieldType('entity_reference');
    $media_fields = [];
    foreach ($map[$entity_type] as $name => $data) {
      foreach ($data['bundles'] as $bundle_name) {
        if ($bundle_name == $bundle) {
          $field_settings = $bundle_fields[$name];
          $target_bundles = $field_settings->getSettings()['handler_settings']['target_bundles'];
          $handler = $field_settings->getSettings()['handler'];
          if (count($target_bundles)) {
            foreach ($target_bundles as $target_bundle) {
              if ($handler == 'default:media' && $target_bundle == $target_media_bundle) {
                //$media_fields[$name] = $field_settings;
                $this->output()->writeln('Found existing media field: ' . $name);
              }
            }
          }
        }
      }
    }

    // Create missing fields
    $missing_fields = array_diff_key($source_fields, $media_fields);

    foreach ($missing_fields as $new_field_name => $field) {
      try {
        $new_field = $this->createMediaField(
          $entity_type,
          $bundle,
          $field,
          $new_field_name,
          $target_media_bundle
        );
      } catch (\Exception $ex) {
        $this->output()
          ->writeln("Error while creating media field: {$new_field_name}.");
      }

      if (!empty($new_field)) {
        $this->output()
          ->writeln("Created media field: {$new_field->getName()}.");
      }
    }

    drupal_flush_all_caches();

  }

  /**
   * Create a new entity media reference field.
   *
   * @param $entity_type
   * @param $bundle
   * @param \Drupal\field\Entity\FieldConfig $existing_field
   * @param $new_field_name
   * @param $target_media_bundle
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   */
  private function createMediaField(
    $entity_type,
    $bundle,
    FieldConfig $existing_field,
    $new_field_name,
    $target_media_bundle
  ) {
    $field = FieldConfig::loadByName($entity_type, $bundle, $new_field_name);
    if (empty($field)) {
      $field_storage = FieldStorageConfig::create(
        [
          'field_name' => $new_field_name,
          'entity_type' => $entity_type,
          'cardinality' => $existing_field->getFieldStorageDefinition()
            ->getCardinality(),
          'type' => 'entity_reference',
          'settings' => ['target_type' => 'media'],
        ]
      );
      $field_storage->save();
      $field = entity_create(
        'field_config',
        [
          'field_storage' => $field_storage,
          'bundle' => $bundle,
          'label' => $existing_field->getLabel() . ' Media',
          'settings' => [
            'handler' => 'default:media',
            'handler_settings' => ['target_bundles' => [$target_media_bundle => $target_media_bundle]],
          ],
        ]
      );
      $field->save();

      // Update Form Widget
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $definition */
      $definition = $this->entity_type_manager->getStorage('entity_form_display')
        ->load($entity_type . '.' . $bundle . '.' . 'default');
      $definition->setComponent(
        $new_field_name,
        [
          'type' => 'entity_reference_autocomplete',
        ]
      )->save();

    }
    return $field;
  }

  /**
   * Find duplicate file entities.
   *
   * @command migrate:duplicate-file-detection
   * @aliases migrate-duplicate
   *
   */
  public function duplicateImageDetection() {

    // Only query permanent files.
    $query = $this->connection->select('file_managed', 'f');
    $query->fields('f', ['fid']);
    $query->condition('status', 1, '=');
    $query->leftJoin('migrate_file_to_media_mapping', 'm', 'm.fid = f.fid');
    $query->isNull('m.fid');
    $fids = array_map(
      function ($fid) {
        return $fid->fid;
      },
      $query->execute()->fetchAll()
    );

    $files = File::loadMultiple($fids);

    foreach ($files as $file) {
      /** @var \Drupal\file\Entity\File $file */
      $data = file_get_contents($file->getFileUri());
      $binary_hash = sha1($data);

      $query = $this->connection->select('migrate_file_to_media_mapping', 'map');
      $query->fields('map');
      $query->condition('binary_hash', $binary_hash, '=');
      $result = $query->execute()->fetchObject();

      $duplicate_fid = $file->id();
      if ($result) {
        $existing_file = File::load($result->fid);
        $duplicate_fid = $existing_file->id();
        $this->output()->writeln("Duplicate found for file {$existing_file->id()}");
      }

      $this->connection->insert('migrate_file_to_media_mapping')
        ->fields([
          'type' => 'image',
          'fid' => $file->id(),
          'target_fid' => $duplicate_fid,
          'binary_hash' => $binary_hash,
        ])
        ->execute();

      $this->output()->writeln("Added binary hash {$binary_hash} for file file {$file->id()}");
    }
  }

}
