<?php

namespace Drupal\migrate_file_to_media\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;


/**
 *
 * @MigrateProcessPlugin(
 *   id = "file_id_lookup"
 * )
 */
class FileIdLookup extends ProcessPluginBase {

  protected $source_fields = [];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    if ($value['target_id']) {
      $query = \Drupal::database()->select('migrate_file_to_media_mapping', 'map');
      $query->fields('map');
      $query->condition('fid', $value['target_id'], '=');
      $result = $query->execute()->fetchObject();

      if ($result) {
        return $result->target_fid;
      }

    }
    throw new MigrateSkipRowException();
  }
}
