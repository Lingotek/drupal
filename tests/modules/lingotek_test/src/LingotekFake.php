<?php
namespace Drupal\lingotek_test;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekProfileInterface;
use Drupal\lingotek\Remote\LingotekApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LingotekFake implements LingotekInterface {

  protected $api;
  protected $config;

  public function __construct(LingotekApiInterface $api, LanguageLocaleMapperInterface $language_locale_mapper, ConfigFactoryInterface $config) {
    $this->api = $api;
    $this->languageLocaleMapper = $language_locale_mapper;
    $this->config = $config->getEditable('lingotek.settings');
  }

  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('lingotek.api'),
        $container->get('lingotek.language_locale_mapper'),
        $container->get('config.factory')
      );
  }

  public function get($key) {
    switch ($key){
      case 'account':
        if (\Drupal::state()->get('lingotek_fake.logged_in', FALSE) === FALSE ||
          \Drupal::state()->get('lingotek_fake.setup_completed', FALSE) === FALSE) {
          return [];
        }
        else {
          $host = \Drupal::request()->getSchemeAndHttpHost();
          return ['host' => $host,
            'sandbox_host' => $host,
            'authorize_path' => $this->get('account.authorize_path'),
            'default_client_id' => $this->get('account.default_client_id'),
            'access_token' => 'test_token',
            'login_id' => 'testUser@example.com',
          ];
        }
      case 'account.login_id':
        return 'testUser@example.com';
      case 'account.sandbox_host':
      case 'account.host':
        return \Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->getBasePath();
      case 'account.authorize_path':
        if (\Drupal::state()->get('authorize_no_redirect', FALSE)) {
          return '/lingofake/authorize_no_redirect';
        }
        return '/lingofake/authorize';
      case 'account.default_client_id':
        return 'test_default_client_id';
      case 'default.community':
        return \Drupal::config('lingotek.settings')->get($key) ? \Drupal::config('lingotek.settings')->get($key): 'test_community';
      case 'default.project':
        return \Drupal::config('lingotek.settings')->get($key) ? \Drupal::config('lingotek.settings')->get($key) : 'test_project';
      case 'default.vault':
        return \Drupal::config('lingotek.settings')->get($key) ? \Drupal::config('lingotek.settings')->get($key) : 'test_vault';
      case 'default.workflow':
        return \Drupal::config('lingotek.settings')->get($key) ? \Drupal::config('lingotek.settings')->get($key) : 'test_workflow';
      case 'profile':
        return [
            ['id' => 1,
              'name' => 'automatic',
              'auto_upload' => TRUE,
              'auto_download'=> TRUE,
            ],
          ];
    }
  }

  public function set($key, $value) {
    \Drupal::configFactory()->getEditable('lingotek.settings')->set($key, $value)->save();
  }

  public function getAccountInfo() {
    \Drupal::state()->set('lingotek_fake.setup_completed', TRUE);
    return [
      'id' => 'test',
      'type' => 'token',
      'client_id' => 'test_default_client_id',
      'user_id' => 'testUser',
      'login_id' => 'testUser@example.com',
      'expires_at' => -1,
    ];
  }

  public function getVaults($force = FALSE) {
    return [
      'test_vault' => 'Test vault',
      'test_vault2' => 'Test vault2',
    ];
  }

  public function getWorkflows($force = FALSE) {
    return [
      'test_workflow' => 'Test workflow',
      'test_workflow2' => 'Test workflow 2',
    ];
  }

  public function getCommunities($force = FALSE) {
    return [
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ];
  }

  public function getProjects($force = FALSE) {
    return [
      'test_project' => 'Test project',
      'test_project2' => 'Test project 2',
    ];
  }

  public function getProject($project_id) {
    return ['properties' => [
      'creation_date' => 1284940800000,
      'workflow_id' => 'test_workflow',
      'callback_url' => '',
      'title' => 'Test project',
      'community_id' => 'test_community',
      'id' => 'test_project',
    ]];
  }

  public function setProjectCallBackUrl($project_id, $callback_url) {
    // We ignore the call and simulate a success.
    return TRUE;
  }

  public function getResources($force = FALSE) {
    return [
      'project' => $this->getProjects($force),
      'vault' => $this->getVaults($force),
      'community' => $this->getCommunities($force),
      'workflow' => $this->getWorkflows($force),
    ];
  }

  public function getDefaults() {
    return [
      'project' => 'test_project',
      'vault' => 'test_vault',
      'community' => 'test_community',
      'workflow' => 'test_workflow',
    ];
  }

  public function uploadDocument($title, $content, $locale, $url = NULL, LingotekProfileInterface $profile = NULL) {
    // If the upload is successful, we must return a valid hash.
    \Drupal::state()->set('lingotek.uploaded_title', $title);
    \Drupal::state()->set('lingotek.uploaded_content', $content);
    \Drupal::state()->set('lingotek.uploaded_locale', $locale);
    \Drupal::state()->set('lingotek.uploaded_url', $url);
    \Drupal::state()->set('lingotek.used_profile', $profile ? $profile->id() : NULL);

    $count = \Drupal::state()->get('lingotek.uploaded_docs', 0);

    $doc_id = 'dummy-document-hash-id';
    if ($count > 0) {
      $doc_id .= '-' . $count;
    }
    ++$count;
    \Drupal::state()->set('lingotek.uploaded_docs', $count);

    return $doc_id;
  }

  public function updateDocument($doc_id, $content, $url = NULL) {
    \Drupal::state()->set('lingotek.uploaded_content', $content);
    \Drupal::state()->set('lingotek.uploaded_content_url', $url);
    // Our document is always imported correctly.
    return TRUE;
  }

  public function documentImported($doc_id) {
    // Our document is always imported correctly.
    return TRUE;
  }

  public function addTarget($doc_id, $locale, LingotekProfileInterface $profile = NULL) {
    \Drupal::state()->set('lingotek.added_target_locale', $locale);
    \Drupal::state()->set('lingotek.used_profile', $profile ? $profile->id() : NULL);
    // Added locale as target.
    return TRUE;
  }

  public function getDocumentStatus($doc_id) {
    // Translation is done.
    return TRUE;
  }

  public function getDocumentTranslationStatus($doc_id, $locale) {
    \Drupal::state()->set('lingotek.checked_target_locale', $locale);
    // Translation is done.
    return TRUE;
  }

  public function downloadDocument($doc_id, $locale) {
    \Drupal::state()->set('lingotek.downloaded_locale', $locale);
    $type = \Drupal::state()->get('lingotek.uploaded_content_type', 'node');
    switch ($type) {
      case 'node':
        return json_decode('{"title":[{"value":"Las llamas son chulas"}],"uid":[{"target_id":"1"}],"status":[{"value":1}],"created":[{"value":1438095034}],"changed":[{"value":1438095483}],"promote":[{"value":1}],"sticky":[{"value":0}],"revision_log":[{"value":""}],"revision_translation_affected":[{"value":"1"}],"content_translation_source":[{"value":"und"}],"content_translation_outdated":[{"value":"0"}],"body":[{"value":"Las llamas son muy chulas","format":"plain_text","summary":""}]}', TRUE);
      case 'taxonomy_term':
        return json_decode('{"name":[{"value":"Las llamas son chulas"}],"description":[{"value":"Las llamas son muy chulas"}]}', TRUE);
      case 'system.site':
        return json_decode('{"system.site":{"name":"Drupal","slogan":"Las llamas son muy chulas"}}', TRUE);
      case 'content_type':
        return json_decode('{"name":"Artículo","description":"Uso de <em>artículos<\/em> sensibles al tiempo contenidos como noticias, comunicados de prensa o entradas en el blog.","help":""}', TRUE);
      case 'node+taxonomy_term':
        return json_decode('{"title":[{"value":"Las llamas son chulas"}],"body":[{"value":"Las llamas son muy chulas"}],"field_tags":[{"name":[{"value":"Camélido"}]},{"name":[{"value":"Hervíboro"}]}]}', TRUE);
      case 'node+path':
        return json_decode('{"title":[{"value":"Las llamas son chulas"}],"body":[{"value":"Las llamas son muy chulas"}],"path":[{"alias":"/las-llamas-son-chulas"}]}', TRUE);
      case 'node+nullpath':
        return json_decode('{"title":[{"value":"Las llamas son chulas"}],"body":[{"value":"Las llamas son muy chulas"}],"path":[{"alias":null}]}', TRUE);
      case 'node+link':
        return json_decode('{"title":[{"value":"Las llamas son chulas"}],"body":[{"value":"Las llamas son muy chulas"}],"field_link":[{"title":"Enlace con fotos de llamas"}]}', TRUE);
      case 'node+metatag':
        return json_decode('{"title":[{"value":"Las llamas son chulas"}],"body":[{"value":"Las llamas son muy chulas"}],"field_metatag":[{"description":"Este texto ayudará al SEO a encontrar mis llamas."}]}', TRUE);
    }
  }

}
