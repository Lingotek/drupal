/**
 * @file
 * Lingotek target actions JS code for lingotek target actions button.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Process paragraph_actions elements.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches lingotekTargetActions behaviors.
   */
  Drupal.behaviors.lingotekTargetActions = {
    attach: function (context, settings) {
      var $actionsElement = $(context).find('.lingotek-target-dropdown').once('lingotek-target-dropdown');
      // Attach event handlers to toggle button.
      $actionsElement.each(function () {
        var $this = $(this);
        var $toggle = $this.find('.lingotek-target-dropdown-toggle');

        $toggle.on('click', function (e) {
          e.preventDefault();
          $this.toggleClass('open');
        });

        $this.on('focusout', function (e) {
          setTimeout(function () {
            if ($this.has(document.activeElement).length == 0) {
              // The focus left the action button group, hide actions.
              $this.removeClass('open');
            }
          }, 1);
        });
      });
    }
  };

})(jQuery, Drupal);
