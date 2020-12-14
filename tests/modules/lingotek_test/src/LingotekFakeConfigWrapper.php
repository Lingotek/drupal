<?php

namespace Drupal\lingotek_test;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LingotekFakeConfigWrapper extends Config {

  public $config;

  public function __construct($name, StorageInterface $storage, EventDispatcherInterface $event_dispatcher, TypedConfigManagerInterface $typed_config, Config $config) {
    parent::__construct($name, $storage, $event_dispatcher, $typed_config);
    $this->config = $config;
  }

  public function get($key = '') {
    switch ($key) {
      case 'account':
        if (\Drupal::state()->get('lingotek_fake.logged_in', FALSE) === FALSE ||
          \Drupal::state()->get('lingotek_fake.setup_completed', FALSE) === FALSE) {
          return [];
        }
        else {
          $host = \Drupal::request()->getSchemeAndHttpHost();
          return [
            'host' => $host,
            'authorize_path' => $this->get('account.authorize_path'),
            'default_client_id' => $this->get('account.default_client_id'),
            'access_token' => $this->get('account.access_token'),
            'login_id' => $this->get('account.login_id'),
            'use_production' => TRUE,
          ];
        }

      case 'account.login_id':
        if (\Drupal::state()->get('must_remain_disconnected', FALSE)) {
          return $this->config->get($key);
        }
        return $this->config->get($key) ? $this->config->get($key) : 'testUser@example.com';

      case 'account.access_token':
        if (\Drupal::state()->get('must_remain_disconnected', FALSE)) {
          return $this->config->get($key);
        }
        return $this->config->get($key) ? $this->config->get($key) : 'test_token';

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

      case 'default':
        return $this->config->get($key) ? $this->config->get($key) : [
          'project' => 'test_project',
          'vault' => 'test_vault',
          'filter' => 'drupal_default',
          'subfilter' => 'drupal_default',
          'community' => 'test_community',
          'workflow' => 'machine_translation',
        ];

      case 'default.community':
        return $this->config->get($key) ? $this->config->get($key) : 'test_community';

      case 'default.project':
        return $this->config->get($key) ? $this->config->get($key) : 'test_project';

      case 'default.vault':
        return $this->config->get($key) ? $this->config->get($key) : 'test_vault';

      case 'default.filter':
        return $this->config->get($key) ? $this->config->get($key) : 'drupal_default';

      case 'default.subfilter':
        return $this->config->get($key) ? $this->config->get($key) : 'drupal_default';

      case 'default.workflow':
        return $this->config->get($key) ? $this->config->get($key) : 'machine_translation';

      case 'account.resources.project':
        if (!$this->config->get($key)) {
          $projects = [
            'test_project' => 'Test project',
            'test_project2' => 'Test project 2',
          ];
          $this->set($key, $projects)->save();
        }
        return $this->config->get($key) ? $this->config->get($key) : [];

      case 'account.resources.workflow':
        if (!$this->config->get($key)) {
          $workflows = [
            'machine_translation' => 'Machine Translation',
            'test_workflow' => 'Test workflow',
            'test_workflow2' => 'Test workflow 2',
          ];
          $this->set($key, $workflows)->save();
        }
        return $this->config->get($key) ? $this->config->get($key) : [];

      case 'account.resources.community':
        if (!$this->config->get($key)) {
          $communities = [
            'test_community' => 'Test community',
            'test_community2' => 'Test community 2',
          ];
          if (!$this->config instanceof ImmutableConfig) {
            $this->set($key, $communities)->save();
          }
        }
        return $this->config->get($key) ? $this->config->get($key) : [];

      case 'account.resources.vault':
        if (!$this->config->get($key)) {
          $vaults = [
            'test_vault' => 'Test vault',
            'test_vault2' => 'Test vault 2',
          ];
          $this->set($key, $vaults)->save();
        }
        return $this->config->get($key) ? $this->config->get($key) : [];

      case 'account.resources.filter':
        if (!$this->config->get($key)) {
          $default_filters = [
            'test_filter' => 'Test filter',
            'test_filter2' => 'Test filter 2',
            'test_filter3' => 'Test filter 3',
          ];
          $filters = [];
          if (!\Drupal::state()->get('lingotek.no_filters', FALSE)) {
            $filters = $default_filters;
          }
          $this->set($key, $filters)->save();
        }
        return $this->config->get($key) ? $this->config->get($key) : [];

      default:
        return $this->config->get($key);
    }
  }

  public function set($key, $value) {
    if (!$this->config instanceof ImmutableConfig) {
      parent::set($key, $value);
      $this->config->set($key, $value);
    }
    return $this;
  }

  public function save($has_trusted_data = FALSE) {
    if (!$this->config instanceof ImmutableConfig) {
      parent::save($has_trusted_data);
      $this->config->save($has_trusted_data);
    }
    return $this;
  }

  public function getRawData() {
    return $this->config->getRawData();
  }

  public function clear($key) {
    if (!$this->config instanceof ImmutableConfig) {
      parent::clear($key);
      $this->config->clear($key);
    }
    return $this;
  }

}
