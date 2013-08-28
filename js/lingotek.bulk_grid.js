/**
 * @file
 * Custom javascript.
 */
function lingotek_perform_action(nid, action) {
  jQuery('#edit-grid-container .form-checkbox').removeAttr('checked');
  jQuery('#edit-the-grid-' + nid).attr('checked', 'checked');
  jQuery('#edit-actions-select').val(action);
  jQuery('#edit-actions-select').trigger('change');
}
  
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
  
  function lingotekTriggerModal(id, nids) {
    nids = [];
    $('#edit-grid-container .form-checkbox').each(function() {
      if($(this).attr('checked')) {
        val = $(this).val();
        if(val != 'on') {
          nids.push(val);
        }
      }
    });
    
    $('#edit-actions-select').val('select');
    url = $(id).attr('href');
    ob = Drupal.ajax[url];
    ob.element_settings.url = ob.options.url = ob.url = url + '/' + nids.join(',');
    $(id).trigger('click');
    $(id).attr('href', url);
    $('.modal-header .close').click( function() {
      location.reload();
    });
  }
  
  Drupal.behaviors.lingotekBulkGrid = {
    attach: function (context) {
      $('input#edit-submit-changes.form-submit').hide();
      $('#edit-limit-select').change(function() {
        $('input#edit-submit-changes.form-submit').trigger('click');
      });
      
      $('.form-item-actions-select').hide();
      
      
      $('.select-all').change(function() {
        lingotekUpdateActionVisibilty();
      });
      
            
      $('input#edit-actions-submit.form-submit').hide();
      $('#edit-actions-select').change(function() {
        val = $('#edit-actions-select').val();
        
        if(val == 'reset') {
          lingotekTriggerModal('#reset-translations-link');
        } else if(val == 'edit') {
          lingotekTriggerModal('#edit-settings-link');
        } else  {
          $('input#edit-actions-submit.form-submit').trigger('click');
        }
      });
      
      $('#edit-grid-container .form-checkbox').change(function() {
        lingotekUpdateActionVisibilty();
      });
  }
};

})(jQuery);
