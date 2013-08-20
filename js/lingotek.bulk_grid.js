/**
 * @file
 * Custom javascript.
 */

(function ($) {
  function lingotekUpdateActionVisibilty() {
    var count = 0;

    $('#edit-grid-container .form-checkbox').each(function() {
      var isChecked = $(this).attr('checked');
      count += isChecked ? 1 : 0;
    });

    if (count > 0) {
      $('.form-item-actions-select').show();
    } else {
      $('.form-item-actions-select').hide();
    }
  }
  
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
      
      $('.form-item-actions-select').hide();
      
      
      $('.select-all').change(function() {
        lingotekUpdateActionVisibilty();
      });
      
            
      $('input#edit-actions-submit.form-submit').hide();
      $('#edit-actions-select').change(function() {
        $('input#edit-actions-submit.form-submit').trigger('click');
      });
      
      $('#edit-grid-container .form-checkbox').change(function() {
        lingotekUpdateActionVisibilty();
      });
  }
};

})(jQuery);
