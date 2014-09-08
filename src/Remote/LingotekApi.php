<?php

/**
 * @file
 * Contains \Drupal\lingotek\Remote\LingotekApi.
 */

namespace Drupal\lingotek\Remote;

use Drupal\lingotek\Remote\LingotekApiInterface;
use Drupal\lingotek\Remote\LingotekHttp;
use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * a simple connector to the Lingotek Translation API
 */
class LingotekApi implements LingotekApiInterface {
  
  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \Drupal\lingotek\Remote\LingotekHttp
   */
  protected $lingotekClient;

  /**
   * Constructs a LingotekApi object.
   */
  public function __construct(LingotekHttp $lingotek_http_client) {
    $this->lingotekClient = $lingotek_http_client;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.http_client')
    );
  }

  public function getAccountInfo() {
    $access_token = $this->lingotekClient->getCurrentToken();
    $account_info = $this->lingotekClient->get('/auth/oauth2/access_token_info?access_token=' . $access_token);
    return $account_info;
  }

  public function uploadDocument($args) {
  }

  public function patchDocument($id, $args) {
  }

  public function deleteDocument($id) {
  }

  public function getDocuments($args = array()) {
  }

  public function documentExists($id) {
  }

  public function getTranslationStatus($id) {
  }

  public function requestTranslation($id, $locale) {
  }

  public function getTranslation($id, $locale) {
  }

  public function deleteTranslation($id, $locale) {
  }

  public function getConnectUrl($redirect_uri) {
  }

  public function getCommunities() {
  }

  public function getProjects($community_id) {
  }

  public function getVaults($community_id) {
  }

  public function getWorkflows($community_id) {
  }
}
