/**
 * @file
 * Custom javascript.
 */

(function ($) {
  Drupal.behaviors.lingotekBulkGrid = {
    attach: function (context) {
      $('input#edit-submit-changes.form-submit').hide();
      $('#edit-header-fieldset .form-item select').change(function() {
        $('input#edit-submit-changes.form-submit').trigger('click');
      });
/*      $('#edit-grid-container #modal-link').click(function() {
        $('#edit-grid-container #modal-link').dialog({
          modal: true
        });
      });
     /* $('#edit-grid-container #modal-link').dialog({
        autoOpen: false,
        height: 823,
        width: 1200,
        modal: true
      });*/
  }
};

})(jQuery);
