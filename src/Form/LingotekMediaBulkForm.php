<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;

/**
 * Special treatment for Media in bulk form.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekMediaBulkForm {

  use StringTranslationTrait;

  /**
   * Adds the thumbnail for a media item as the first column.
   *
   * @param array &$form
   *   The form definition array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function form(array &$form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    if ($form['#form_id'] === 'lingotek_management' && $build_info && isset($build_info['form_id']) && ($build_info['form_id'] === 'lingotek_management')) {
      $formObject = $build_info['callback_object'];
      if ($formObject->getEntityTypeId() === 'media') {
        $thumbnailExists = ImageStyle::load('thumbnail');
        $mids = array_keys($form['table']['#options']);
        /** @var \Drupal\media\MediaInterface[] $medias */
        $medias = Media::loadMultiple($mids);
        $form['table']['#header'] = ['thumbnail' => $this->t('Thumbnail')] + $form['table']['#header'];
        foreach ($medias as $id => $media) {
          $displayOptions = [
            'label' => 'hidden',
          ];
          if ($thumbnailExists) {
            $displayOptions['settings']['image_style'] = 'thumbnail';
          }
          $form['table']['#options'][$id]['thumbnail']['data'] = $media->get('thumbnail')->view($displayOptions);
        }
      }
    }
  }

}
