<?php

/**
 * @file
 * Contains \Drupal\lingotek\Remote\LingotekApiInterface.
 */

namespace Drupal\lingotek\Remote;

use Symfony\Component\DependencyInjection\ContainerInterface;

interface LingotekApiInterface {
  
  public static function create(ContainerInterface $container);
  public function getAccountInfo();
  public function uploadDocument($args);
  public function patchDocument($id, $args);
  public function deleteDocument($id);
  public function getDocument($id);
  public function documentExists($id);
  public function getTranslationStatus($id);
  public function requestTranslation($id, $locale);
  public function getTranslation($id, $locale);
  public function deleteTranslation($id, $locale);
  public function getConnectUrl($redirect_uri);
  public function getCommunities();
  public function getProjects($community_id);
  public function getVaults($community_id);
  public function getWorkflows($community_id);
};
