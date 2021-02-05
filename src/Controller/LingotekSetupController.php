<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\lingotek\Form\LingotekSettingsAccountForm;
use Drupal\lingotek\Form\LingotekSettingsCommunityForm;
use Drupal\lingotek\Form\LingotekSettingsConnectForm;
use Drupal\lingotek\Form\LingotekSettingsDefaultsForm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekSetupController extends LingotekControllerBase {

  /**
   * Presents a connection page to Lingotek Services
   *
   * @return array
   *   The connection form.
   */
  public function accountPage() {
    if ($this->setupCompleted()) {
      return $this->formBuilder->getForm(LingotekSettingsAccountForm::class);
    }
    return [
      '#type' => 'markup',
      'markup' => $this->formBuilder->getForm(LingotekSettingsConnectForm::class),
    ];
  }

  public function handshake(Request $request) {
    if (Request::METHOD_POST === $request->getMethod()) {
      $body = Json::decode($request->getContent());
      if (isset($body['access_token'])) {
        $config = \Drupal::configFactory()->getEditable('lingotek.settings');
        $config->set('account.access_token', $body['access_token']);
        $config->set('account.use_production', TRUE);
        $config->save();

        $account_info = $this->fetchAccountInfo();
        $this->saveAccountInfo($account_info);
        $this->messenger()
          ->addStatus($this->t('Your account settings have been saved.'));
        $this->logger->notice('Account connected to Lingotek.');
        return new JsonResponse([
          'status' => TRUE,
          'message' => 'Account connected to Lingotek.',
        ]);
      }
      else {
        return new JsonResponse([
          'status' => FALSE,
          'message' => 'Account not connected to Lingotek.',
        ]);
      }
    }
    elseif (Request::METHOD_GET === $request->getMethod()) {
      // Is a GET.
      $config = \Drupal::config('lingotek.settings');
      if ($config->get('account.access_token')) {
        // No need to show the username and token if everything worked correctly
        // Just go to the community page
        return $this->redirect('lingotek.setup_community');
      }
      // Is a GET, but don't have the token yet.
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Connecting... Please wait to be redirected'),
        '#attached' => ['library' => ['lingotek/lingotek.connect']],
      ];
    }
  }

  public function communityPage() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $communities = $this->lingotek->getCommunities(TRUE);
    if (empty($communities)) {
      // TODO: Log an error that no communities exist.
      return $this->redirect('lingotek.setup_account');
    }
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('account.resources.community', $communities);
    $config->save();
    if (count($communities) == 1) {
      // No choice necessary. Save and advance to next page.
      $config->set('default.community', current(array_keys($communities)));
      $config->save();
      // update resources based on newly selected community
      $this->lingotek->getResources(TRUE);
      return $this->redirect('lingotek.setup_defaults');
    }
    return [
      '#type' => 'markup',
      'markup' => $this->formBuilder->getForm(LingotekSettingsCommunityForm::class),
    ];
  }

  public function defaultsPage() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $resources = $this->lingotek->getResources();
    // No choice necessary. Save and advance to the next page.
    if (count($resources['project']) == 1 && count($resources['vault']) == 1) {
      $config = \Drupal::configFactory()->getEditable('lingotek.settings');

      $config->set('default.project', current(array_keys($resources['project'])));
      $config->set('default.vault', current(array_keys($resources['vault'])));
      $default_workflow = array_search('Machine Translation', $resources['workflow']);
      if ($default_workflow === FALSE) {
        $default_workflow = current(array_keys($resources['workflow']));
      }
      $config->set('default.workflow', $default_workflow);
      // Assign the project callback
      $new_callback_url = \Drupal::urlGenerator()->generateFromRoute('lingotek.notify', [], ['absolute' => TRUE]);
      $config->set('account.callback_url', $new_callback_url);
      $config->save();
      $new_response = $this->lingotek->setProjectCallBackUrl($config->get('default.project'), $new_callback_url);
      return $this->redirect('lingotek.dashboard');
    }
    return [
      '#type' => 'markup',
      'markup' => $this->formBuilder->getForm(LingotekSettingsDefaultsForm::class),
    ];
  }

  protected function saveToken($token) {
    if (!empty($token)) {
      \Drupal::configFactory()->getEditable('lingotek.settings')->set('account.access_token', $token)->save();
    }
  }

  protected function saveAccountInfo($account_info) {
    if (!empty($account_info)) {
      $config = \Drupal::configFactory()->getEditable('lingotek.settings');
      $config->set('account.login_id', $account_info['login_id']);
      $config->save();
    }
  }

  protected function fetchAccountInfo() {
    return $this->lingotek->getAccountInfo();
  }

}
