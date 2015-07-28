<?php
namespace Drupal\lingotek_test;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\Remote\LingotekApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LingotekFake implements LingotekInterface {

  protected $api;
  protected $config;

  public function __construct(LingotekApiInterface $api, ConfigFactoryInterface $config) {
    $this->api = $api;
    $this->config = $config->getEditable('lingotek.settings');
  }

  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('lingotek.api'),
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
      case 'account.sandbox_host':
      case 'account.host':
        return \Drupal::request()->getSchemeAndHttpHost();
      case 'account.authorize_path':
        return 'lingofake/authorize';
      case 'account.default_client_id':
        return 'test_default_client_id';
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
    // We ignore all calls.
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
      'resource' => 'test_resource',
    ];
  }
}
