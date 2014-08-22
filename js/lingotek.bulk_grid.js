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
  function lingotekTriggerModal(self) {
    var $self = $(self);
    url = $self.attr('href');
    var entity_ids = [];
    $('#edit-grid-container .form-checkbox').each(function() {
      if($(this).attr('checked')) {
        val = $(this).val();
        if(val != 'on') {
          entity_ids.push(val);
        }
      }
    });
    console.log(entity_ids);
    if(entity_ids.length > 0) {
      $('#edit-actions-select').val('select');
      ob = Drupal.ajax[url];
      ob.element_settings.url = ob.options.url = ob.url = url + '/' + entity_ids.join(',');
      $self.trigger('click');
      $self.attr('href', url);
      $('.modal-header .close').click( function() {
        location.reload();
      });
    } else {
      var $console = $('#console').length ? $('#console') : $("#lingotek-console");
      $console.html(Drupal.t('<div class="messages warning"><h2 class="element-invisible">Warning message</h2>You must select at least one entity to perform this action.</div>'));
    }
  }

  var message_shown = false;

  Drupal.behaviors.lingotekBulkGrid = {
    attach: function (context) {
      $('.form-checkbox').change(function() {
        if (message_shown !== true) {
          $('#edit-grid-container').prepend('<div class="messages warning">All items in the same config set will be updated simultaneously, therefore some checkboxes are automatically checked to indicate that.</div>');
          message_shown = true;
        }
        var cells_of_selected_row = $(this).parents("tr").children();

        var selected_set_name = cells_of_selected_row.children('.set_name').text();

        var rows_in_same_set = $("tr").children().children('.set_name:contains("' + selected_set_name + '")').parent().parent();

        var rows_with_incompletes = rows_in_same_set.children().children('.target-pending, .target-ready').parent().parent();
        var checkboxes = rows_with_incompletes.children().children().children("input");
        var all_chechboxes_in_set = rows_in_same_set.children().children().children("input");
        if ($(this).is(':checked')) {
          checkboxes.attr('checked',true);
          rows_with_incompletes.addClass('selected');
        }
        else if ($(this).parents("tr").children().children('.target-pending, .target-ready').length) {
          all_chechboxes_in_set.attr('checked',false);
          rows_in_same_set.removeClass('selected');
        }
        else {
          // only uncheck the box that was clicked
        }
      });

      $('input#edit-actions-submit.form-submit').hide();
      $('#edit-actions-select').change(function() {
        val = $(this).val();

        if(val == 'reset' || val == 'delete') {
          lingotekTriggerModal($('#'+val+'-link'));
        } else if(val == 'edit') {
          lingotekTriggerModal($('#edit-settings-link'));
        } else if(val == 'workflow') {
          lingotekTriggerModal($('#change-workflow-link'));
        } else  {
          $('input#edit-actions-submit.form-submit').trigger('click');
        }
      });

      $('#edit-limit-select').change(function() {
        $('#edit-search-submit.form-submit').trigger('click');
      });
  }
};

})(jQuery);
