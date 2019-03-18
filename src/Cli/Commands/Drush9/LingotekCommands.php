<?php

namespace Drupal\lingotek\Cli\Commands\Drush9;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\lingotek\Cli\LingotekCliService;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

/**
 * A Drush9 compatible commandfile with Lingotek operations.
 */
class LingotekCommands extends DrushCommands {

  /**
   * The Lingotek CLI service.
   *
   * @var \Drupal\lingotek\Cli\LingotekCliService
   */
  protected $cliService;

  /**
   * Drush8CommandBase constructor.
   *
   * @param \Drupal\lingotek\Cli\LingotekCliService $cli_service
   *   The Lingotek CLI service.
   */
  public function __construct(LingotekCliService $cli_service) {
    $this->cliService = $cli_service;
  }

  /**
   * Upload content to Lingotek.
   *
   * @param $entity_type_id
   *   The entity type ID. E.g. "node"
   * @param $entity_id
   *   The entity ID. E.g. "2
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option job_id
   *   Job ID to be included.
   * @usage drush ltk-upload node 1
   *   Upload node with ID 1.
   * @usage drush ltk-upload taxonomy_term 3 --job="my_job_identifier"
   *   Upload taxonomy term with ID 3 assigning "my_job_identifier" as Job ID.
   *
   * @command lingotek:upload
   * @aliases ltk-upload,lingotek-upload
   */
  public function upload($entity_type_id, $entity_id, array $options = ['job_id' => NULL]) {
    $this->cliService->setupOutput($this->output());
    $this->cliService->setLogger($this->logger());

    $this->cliService->upload($entity_type_id, $entity_id, $options['job_id']);
  }

  /**
   * Check upload status to Lingotek.
   *
   * @param $entity_type_id
   *   The entity type ID. E.g. "node"
   * @param $entity_id
   *   The entity ID. E.g. "2"
   * @usage drush ltk-check-upload node 1
   *   Check upload status for node with ID 1.
   * @usage drush ltk-check-upload taxonomy_term 3
   *   Check upload status for taxonomy term with ID 3.
   *
   * @command lingotek:check-upload
   * @aliases ltk-source,lingotek-check-upload
   */
  public function checkUpload($entity_type_id, $entity_id) {
    $this->cliService->setupOutput($this->output());
    $this->cliService->setLogger($this->logger());

    $this->cliService->checkUpload($entity_type_id, $entity_id);
  }

  /**
   * Request translations to Lingotek.
   *
   * @param $entity_type_id
   *   The entity type ID. E.g. "node"
   * @param $entity_id
   *   The entity ID. E.g. "2"
   * @option langcodes
   *   A comma delimited list of language codes.
   * @usage drush ltk-request node 1
   *   Request translations for node with ID 1.
   * @usage drush ltk-request taxonomy_term 3 --langcodes=es,it
   *   Request Spanish and Italian translations for taxonomy term with ID 3.
   *
   * @field-labels
   *   langcode: Language code
   * @default-fields langcode
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Statuses of the given translations formatted as a table.
   *
   * @command lingotek:request-translations
   * @aliases ltk-request,lingotek-request-translations
   */
  public function requestTranslations($entity_type_id, $entity_id, $options = ['langcodes' => 'all']) {
    $this->cliService->setupOutput($this->output());
    $this->cliService->setLogger($this->logger());

    $langcodes = StringUtils::csvToArray($options['langcodes']);
    $languages = $this->cliService->requestTranslations($entity_type_id, $entity_id, $langcodes);
    return new RowsOfFields($languages);
  }

  /**
   * Request translations to Lingotek.
   *
   * @param $entity_type_id
   *   The entity type ID. E.g. "node"
   * @param $entity_id
   *   The entity ID. E.g. "2"
   * @usage drush ltk-check-status node 1
   *   Check translation statuses for node with ID 1.
   * @usage drush ltk-check-status taxonomy_term 3 --langcodes=es,it
   *   Check Spanish and Italian translation statuses for taxonomy term with ID 3.
   *
   * @field-labels
   *   langcode: Language code
   *   status: Status
   * @default-fields langcode,status
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Statuses of the given translations formatted as a table.
   *
   * @command lingotek:check-translations-statuses
   * @aliases ltk-check-status,lingotek-check-translations-statuses
   */
  public function checkTranslationsStatuses($entity_type_id, $entity_id, $options = ['langcodes' => 'all']) {
    $this->cliService->setupOutput($this->output());
    $this->cliService->setLogger($this->logger());

    $langcodes = StringUtils::csvToArray($options['langcodes']);
    $languages = $this->cliService->checkTranslationsStatuses($entity_type_id, $entity_id, $langcodes);
    return new RowsOfFields($languages);
  }

  /**
   * Download translations from Lingotek.
   *
   * @param $entity_type_id
   *   The entity type ID. E.g. "node"
   * @param $entity_id
   *   The entity ID. E.g. "2"
   * @usage drush ltk-download node 1
   *   Download translations for node with ID 1.
   * @usage drush ltk-download taxonomy_term 3 --langcodes=es,it
   *   Download Spanish and Italian translations for taxonomy term with ID 3.
   *
   * @command lingotek:download-translations
   * @aliases ltk-download,lingotek-download-translations
   */
  public function downloadTranslations($entity_type_id, $entity_id, $options = ['langcodes' => 'all']) {
    $this->cliService->setupOutput($this->output());
    $this->cliService->setLogger($this->logger());

    $langcodes = StringUtils::csvToArray($options['langcodes']);
    $this->cliService->downloadTranslations($entity_type_id, $entity_id, $langcodes);
  }

}
