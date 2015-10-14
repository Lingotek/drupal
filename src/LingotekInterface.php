<?php

namespace Drupal\lingotek;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface LingotekInterface {

  public function get($key);
  public function set($key, $value);
  public static function create(ContainerInterface $container);

  public function getAccountInfo();
  public function getResources($force = FALSE);
  public function getWorkflows($force = FALSE);
  public function getVaults($force = FALSE);
  public function getCommunities($force = FALSE);
  public function getProjects($force = FALSE);
  public function getDefaults();
  public function getProject($project_id);
  public function setProjectCallBackUrl($project_id, $callback_url);

  /**
   * @param string $title
   *   The title of the document as it will be seen in the TMS.
   * @param $content
   *   The content of the document
   * @param string $locale
   *   The Lingotek locale.
   * @param \Drupal\lingotek\LingotekProfileInterface $profile
   *   The profile being used.
   *
   * @return mixed
   */
  public function uploadDocument($title, $content, $locale = NULL, LingotekProfileInterface $profile = NULL);

  public function documentImported($doc_id);
  public function addTarget($doc_id, $locale);
  public function getDocumentStatus($doc_id);
  public function downloadDocument($doc_id, $locale);

}
