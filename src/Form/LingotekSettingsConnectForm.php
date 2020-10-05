<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure Lingotek
 */
class LingotekSettingsConnectForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.connect_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // build the redirecting link for authentication to Lingotek
    $config = $this->configFactory->get('lingotek.settings');
    $host = $config->get('account.host');
    $auth_path = $config->get('account.authorize_path');
    $id = $config->get('account.default_client_id');
    $return_uri = $this->urlGenerator->generateFromRoute('lingotek.setup_account_handshake', ['success' => 'true', 'prod' => 'prod'], ['absolute' => TRUE]);

    $lingotek_register_link = $host . '/' . 'lingopoint/portal/requestAccount.action?client_id=' . $id . '&response_type=token&app=' . urlencode($return_uri);
    $lingotek_connect_link = $host . '/' . $auth_path . '?client_id=' . $id . '&response_type=token&redirect_uri=' . urlencode($return_uri);
    $lingotek_demo_link = 'https://www.lingotek.com/request-demo';

    $form = [];
    $form['intro_title'] = [
      '#prefix' => '<h1>',
      '#markup' => $this->t('Lingotek | The Translation Network&trade;'),
      '#suffix' => '</h1>',
    ];
    $form['intro_paragraph'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Lingotek is more than an enterprise-class Translation Management System (TMS), it is a completely integrated translation hub that combines an industry-leading cloud TMS, Linguistic Quality Evaluation (LQE), multilingual Application Program Interfaces (API) and connectors, with professional linguists who are experts in using our technology.'),
      '#suffix' => '</p>',
    ];

    $form['money_title'] = [
      '#prefix' => '<h2>',
      '#markup' => $this->t('So How Does Lingotek Make Money?'),
      '#suffix' => '</h2>',
    ];

    $form['money_paragraph'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t("For most of those using the module we don't. That's part of our contribution to the community. In fact, we're the only module that offers the community free machine translation (the cost is covered by Lingotek for up to 100,000 characters). However, a few of the larger Drupal sites that use the module have enterprise business requirements which require direct access to Lingotek's cloud-based TMS. In those cases, we sell them licenses for unrestricted use of our TMS software. Lingotek offers extensive professional translation and localization services. Lingotek's Language Services team includes professional, in-country linguists, localization project managers, and localization engineers - all of whom ensure the highest-quality of translations. Should you have existing translators or vendors, it is possible to continue working with them on the Lingotek TMS. Paid users of Lingotek's cloud-based translation management system (TMS) can leverage customizable workflows based on content type or language as well as leverage linguistic assets such as glossaries, style guides and translation memory."),
      '#suffix' => '</p>',
    ];

    $form['account_types'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_types'],
    ];

    $form['account_types']['existing_account'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_box'],
    ];
    $form['account_types']['existing_account']['title'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('Connect existing account'),
      '#suffix' => '</h3>',
    ];
    $form['account_types']['existing_account']['body'] = [
      '#prefix' => '<div class="lingotek_signup_box_main">',
      '#markup' => $this->t('Connect using your existing Lingotek account.'),
      '#suffix' => '</div>',
    ];

    $form['account_types']['existing_account']['cta'] = [
      '#type' => 'link',
      '#title' => $this->t('Connect Lingotek Account'),
      '#url' => Url::fromUri($lingotek_connect_link),
      '#attributes' => ['class' => ['lingotek_signup_box_cta', 'lingotek_signup_box_main_cta']],
    ];

    $form['account_types']['free_account'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_box'],
    ];
    $form['account_types']['free_account']['title'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('Get Free account'),
      '#suffix' => '</h3>',
    ];
    $form['account_types']['free_account']['body'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Translation Management Dashboard'),
        $this->t('Lingotek Translation Workbench (CAT Tool)'),
        $this->t('Unlimited Languages'),
        $this->t('Drupal Community Support'),
        $this->t('Machine Translation Only (100K Characters)'),
      ],
      '#attributes' => ['class' => 'lingotek_signup_box_main'],
    ];

    $form['account_types']['free_account']['cta'] = [
      '#type' => 'link',
      '#title' => $this->t('Get started'),
      '#url' => Url::fromUri($lingotek_register_link),
      '#attributes' => ['class' => 'lingotek_signup_box_cta'],
    ];

    $form['account_types']['enterprise_account'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'lingotek_signup_box'],
    ];
    $form['account_types']['enterprise_account']['title'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('Get Enterprise account'),
      '#suffix' => '</h3>',
    ];
    $form['account_types']['enterprise_account']['body'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Professional Translation Workflows'),
        $this->t('Translation Memory & Terminology'),
        $this->t('In-Context Translation Workbench'),
        $this->t('Multilingual Drupal Site Audit & Support'),
        $this->t('Translation Project Management'),
        $this->t('Linguistic Quality Evaluation*'),
        $this->t('Multilingual Business Intelligence*'),
      ],
      '#attributes' => ['class' => 'lingotek_signup_box_main'],
    ];

    $form['account_types']['enterprise_account']['cta'] = [
      '#type' => 'link',
      '#title' => $this->t('Contact Lingotek'),
      '#url' => Url::fromUri($lingotek_demo_link),
      '#attributes' => ['class' => 'lingotek_signup_box_cta', 'target' => '_blank'],
    ];

    $form['#attributes']['class'][] = 'lingotek_signup';
    $form['#attached']['library'][] = 'lingotek/lingotek.signup';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // do nothing for now
  }

}
