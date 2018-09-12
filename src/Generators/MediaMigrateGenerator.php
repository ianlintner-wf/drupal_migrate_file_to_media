<?php

namespace Drupal\migrate_file_to_media\Generators;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Automatically generates yml files for migrations.
 */
class MediaMigrateGenerator extends BaseGenerator {

  protected $name = 'd8:yml:migrate_file_to_media_migration_media';

  protected $alias = 'mf2m_media';

  protected $description = 'Generates yml for File to Media Migration';

  protected $templatePath = __DIR__;

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {

    /** @var \Symfony\Component\Console\Question\Question[] $questions */
    $questions = Utils::defaultPluginQuestions() + [
      'migration_group' => new Question('Migration Group', 'media'),
      'entity_type' => new Question('Entity Type', 'node'),
      'source_bundle' => new Question('Source Bundle', ''),
      'source_field_name' => new Question('Source Field Names (comma separated)', 'field_image'),
      'target_bundle' => new Question('Target Media Type', 'image'),
      'target_field' => new Question('Target Field', 'field_media_image'),
      'lang_code' => new Question('Language Code', 'en'),
      'translation_languages' => new Question('Translation languages (comma separated)', 'de'),
    ];

    $vars = &$this->collectVars($input, $output, $questions);

    $vars['translation_language'] = NULL;

    if ($vars['translation_languages']) {
      $vars['translation_languages'] = array_map('trim', explode(',', strtolower($vars['translation_languages'])));
    }

    if ($vars['source_field_name']) {
      $vars['source_field_name'] = array_map('trim', explode(',', strtolower($vars['source_field_name'])));
    }

    $this->addFile()
      ->path('config/install/migrate_plus.migration.{plugin_id}_step1.yml')
      ->template('media-migration-step1.yml.twig')
      ->vars($vars);

    $this->addFile()
      ->path('config/install/migrate_plus.migration.{plugin_id}_step2.yml')
      ->template('media-migration-step2.yml.twig')
      ->vars($vars);

    foreach ($vars['translation_languages'] as $language) {
      $vars['translation_language'] = $vars['lang_code'] = $language;

      $this->addFile()
        ->path("config/install/migrate_plus.migration.{plugin_id}_step1_{$language}.yml")
        ->template('media-migration-step1.yml.twig')
        ->vars($vars);
    }

  }

}