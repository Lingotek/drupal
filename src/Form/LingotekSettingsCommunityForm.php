<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsCommunityForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure text display settings for this page.
 */
class LingotekSettingsCommunityForm extends ConfigFormBase {

  /**
   * Constructs a \Drupal\lingotek\Form\LingotekSettingsCommunityForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactory $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.setup_community_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('lingotek.settings');
    $case = $config->get('case');
    $form['lingotek_case'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Configure Lingotek World Text'),
      '#default_value' => $case,
      '#options' => array(
        'upper' => $this->t('UPPER'),
        'title' => $this->t('Title'),
      ),
      '#description' => $this->t('Choose the case of your "Lingotek, world!" message.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->get('lingotek.settings')
      ->set('case', $form_state['values']['lingotek_case'])
      ->save();

    parent::submitForm($form, $form_state);
  }
}
