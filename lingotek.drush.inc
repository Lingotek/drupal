<?php

/**
 * @file
 * Drush 8 integration for Lingotek.
 */

use Drupal\lingotek\Cli\Commands\Drush8\Drush8IoWrapper;

/**
 * Implements hook_drush_command().
 */
function lingotek_drush_command() {

  $commands['lingotek-upload'] = [
    'description' => 'Upload content to Lingotek.',
    'aliases' => ['ltk-upload'],
    'arguments' => [
      'entity_type_id' => 'The entity type ID. E.g. "node"',
      'entity_id' => 'The entity ID. E.g. "2',
    ],
    'required-arguments' => TRUE,
    'options' => [
      'job_id' => 'Job ID to be included.',
    ],
    'examples' => [
      'drush ltk-upload node 1' => 'Upload node with ID 1.',
      'drush ltk-upload taxonomy_term 3 --job="my_job_identifier"' => 'Upload taxonomy term with ID 3 assigning "my_job_identifier" as Job ID.',
    ],
    'category' => 'Lingotek translation',
  ];
  $commands['lingotek-check-upload'] = [
    'description' => 'Check upload status to Lingotek.',
    'aliases' => ['ltk-source'],
    'arguments' => [
      'entity_type_id' => 'The entity type ID. E.g. "node"',
      'entity_id' => 'The entity ID. E.g. "2"',
    ],
    'required-arguments' => TRUE,
    'examples' => [
      'drush ltk-check-upload node 1' => 'Check upload status for node with ID 1.',
      'drush ltk-check-upload taxonomy_term 3' => 'Check upload status for taxonomy term with ID 3.',
    ],
    'category' => 'Lingotek translation',
  ];
  $commands['lingotek-request-translations'] = [
    'description' => 'Request translations to Lingotek.',
    'aliases' => ['ltk-request'],
    'arguments' => [
      'entity_type_id' => 'The entity type ID. E.g. "node"',
      'entity_id' => 'The entity ID. E.g. "2"',
    ],
    'options' => [
      'langcodes' => 'A comma delimited list of language codes.',
    ],
    'required-arguments' => TRUE,
    'examples' => [
      'drush ltk-request node 1' => 'Request translations for node with ID 1.',
      'drush ltk-request taxonomy_term 3 --langcodes=es,it' => 'Request Spanish and Italian translations for taxonomy term with ID 3.',
    ],
    'category' => 'Lingotek translation',
  ];
  $commands['lingotek-check-translations-statuses'] = [
    'description' => 'Request translations to Lingotek.',
    'aliases' => ['ltk-check-status'],
    'arguments' => [
      'entity_type_id' => 'The entity type ID. E.g. "node"',
      'entity_id' => 'The entity ID. E.g. "2"',
    ],
    'options' => [
      'langcodes' => 'A comma delimited list of language codes.',
    ],
    'required-arguments' => TRUE,
    'examples' => [
      'drush ltk-check-status node 1' => 'Check translation statuses for node with ID 1.',
      'drush ltk-check-status taxonomy_term 3 --langcodes=es,it' => 'Check Spanish and Italian translation statuses for taxonomy term with ID 3.',
    ],
    'category' => 'Lingotek translation',
  ];
  $commands['lingotek-download-translations'] = [
    'description' => 'Download translations from Lingotek.',
    'aliases' => ['ltk-download'],
    'arguments' => [
      'entity_type_id' => 'The entity type ID. E.g. "node"',
      'entity_id' => 'The entity ID. E.g. "2"',
    ],
    'options' => [
      'langcodes' => 'A comma delimited list of language codes.',
    ],
    'required-arguments' => TRUE,
    'examples' => [
      'drush ltk-download node 1' => 'Download translations for node with ID 1.',
      'drush ltk-download taxonomy_term 3 --langcodes=es,it' => 'Download Spanish and Italian translations for taxonomy term with ID 3.',
    ],
    'category' => 'Lingotek translation',
  ];

  return $commands;
}

function drush_lingotek_upload($entity_type_id, $entity_id) {
  $facade = new Drush8IoWrapper();
  /** @var \Drupal\lingotek\Cli\LingotekCliService $cliService */
  $cliService = \Drupal::service('lingotek.cli_service');
  $cliService->setupOutput($facade);
  $cliService->setLogger($facade);
  $job_id = drush_get_option('job_id', NULL);

  return $cliService->upload($entity_type_id, $entity_id, $job_id);
}

function drush_lingotek_check_upload($entity_type_id, $entity_id) {
  $facade = new Drush8IoWrapper();
  /** @var \Drupal\lingotek\Cli\LingotekCliService $cliService */
  $cliService = \Drupal::service('lingotek.cli_service');
  $cliService->setupOutput($facade);
  $cliService->setLogger($facade);

  return $cliService->checkUpload($entity_type_id, $entity_id);
}

function drush_lingotek_request_translations($entity_type_id, $entity_id) {
  $facade = new Drush8IoWrapper();
  /** @var \Drupal\lingotek\Cli\LingotekCliService $cliService */
  $cliService = \Drupal::service('lingotek.cli_service');
  $cliService->setupOutput($facade);
  $cliService->setLogger($facade);

  $langcodes = _convert_csv_to_array(drush_get_option('langcodes', 'all'));

  $table = $cliService->requestTranslations($entity_type_id, $entity_id, $langcodes);
  drush_print_table($table);
}

function drush_lingotek_check_translations_statuses($entity_type_id, $entity_id) {
  $facade = new Drush8IoWrapper();
  /** @var \Drupal\lingotek\Cli\LingotekCliService $cliService */
  $cliService = \Drupal::service('lingotek.cli_service');
  $cliService->setupOutput($facade);
  $cliService->setLogger($facade);

  $langcodes = _convert_csv_to_array(drush_get_option('langcodes', 'all'));

  $statuses = $cliService->checkTranslationsStatuses($entity_type_id, $entity_id, $langcodes);
  $table = [
    [dt('Language'), dt('Status')],
  ];
  foreach ($statuses as $status) {
    $table[] = [$status['langcode'], $status['status']];
  }
  drush_print_table($table);
}

function drush_lingotek_download_translations($entity_type_id, $entity_id) {
  $facade = new Drush8IoWrapper();
  /** @var \Drupal\lingotek\Cli\LingotekCliService $cliService */
  $cliService = \Drupal::service('lingotek.cli_service');
  $cliService->setupOutput($facade);
  $cliService->setLogger($facade);

  $langcodes = _convert_csv_to_array(drush_get_option('langcodes', 'all'));

  return $cliService->downloadTranslations($entity_type_id, $entity_id, $langcodes);
}
