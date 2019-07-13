<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekProfileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for lingotek profiles deletion.
 */
class LingotekProfileDeleteForm extends EntityDeleteForm {

  /**
   * The Lingotek profiles usage service.
   *
   * @var \Drupal\lingotek\LingotekProfileUsageInterface
   */
  protected $profileUsage;

  /**
   * Constructs a new LingotekProfileDeleteForm object.
   *
   * @param \Drupal\lingotek\LingotekProfileUsageInterface $profile_usage
   *   The Lingotek profiles usage service.
   */
  public function __construct(LingotekProfileUsageInterface $profile_usage) {
    $this->profileUsage = $profile_usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.profile_usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('lingotek.settings');
  }

  /**
   * @inheritDoc
   */
  public function delete(array $form, FormStateInterface $form_state) {
    parent::delete($form, $form_state);
    $form_state->setRedirect('lingotek.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $profile = $this->getEntity();

    $usages = $this->profileUsage->isUsedByContent($profile) | $this->profileUsage->isUsedByConfig($profile) | $this->profileUsage->isUsedByContentSettings($profile);
    if (!$usages) {
      $profile->delete();
      $this->messenger()->addStatus($this->t('The lingotek profile %profile has been deleted.', ['%profile' => $profile->label()]));
    }
    else {
      $this->messenger()->addError($this->t('The Lingotek profile %profile is being used so cannot be deleted.',
        ['%profile' => $profile->label()]));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
