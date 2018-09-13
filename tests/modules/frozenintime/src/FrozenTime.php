<?php

namespace Drupal\frozenintime;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Time implementation were time is frozen
 *
 * @package Drupal\frozenintime
 */
class FrozenTime implements TimeInterface {

  const MY_BIRTHDAY = 493997820;

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    return static::MY_BIRTHDAY;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime() {
    return static::MY_BIRTHDAY;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime() {
    return static::MY_BIRTHDAY;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime() {
    return static::MY_BIRTHDAY;
  }

}
