/**
 * @file
 * Custom javascript.
 */
function lingotek_perform_action(nid, action) {
  jQuery('#edit-grid-container .form-checkbox').removeAttr('checked');
  jQuery('#edit-the-grid-' + nid).attr('checked', 'checked');
  jQuery('#edit-select-actions').val(action);
  jQuery('#edit-select-actions').trigger('change');
}

(function ($) {
  function lingotek_trigger_modal(self) {
    var $self = $(self);
    url = $self.attr('href');
    var entity_ids = [];
    $('#edit-grid-container .form-checkbox').each(function () {
      if ($(this).attr('checked')) {
        val = $(this).val();
        if (val != 'on') {
          entity_ids.push(val);
        }
      }
    });
    if (entity_ids.length > 0) {
      $('#edit-select-actions').val('select');
      ob = Drupal.ajax[url];
      ob.element_settings.url = ob.options.url = ob.url = url + '/' + entity_ids.join(',');
      $self.trigger('click');
      $self.attr('href', url);
      $('.modal-header .close').click(function () {
        location.reload();
      });
    } else {
      var $console = $('#console').length ? $('#console') : $("#lingotek-console");
      $console.html(Drupal.t('<div class="messages warning"><h2 class="element-invisible">Warning message</h2>You must select at least one entity to perform this action.</div>'));
    }
  }

  var message_already_shown = false;

  Drupal.behaviors.lingotekBulkGrid = {
    attach: function (context) {
      $('.form-checkbox').change(function () {
        var cells_of_selected_row = $(this).parents("tr").children();

        var selected_set_name = cells_of_selected_row.children('.set_name').text();

        var rows_in_same_set = $("tr").children().children('.set_name:contains("' + selected_set_name + '")').parent().parent();

        var rows_with_incompletes = rows_in_same_set.children().children('.target-pending, .target-ready, .target-edited').parent().parent();
        var boxes_checked = rows_in_same_set.children().children().children("input:checkbox:checked").length;
        if ($(this).is(':checked')) {
          rows_with_incompletes.addClass('selected');
        }
        else if (boxes_checked <= 0) {
          rows_in_same_set.removeClass('selected');
        }
        else {
          // only uncheck the box that was clicked
        }
        var this_row_incomplete = $.inArray($(this).parents('tr')[0], rows_with_incompletes) !== -1;
        var other_rows_with_incompletes = rows_with_incompletes.length - this_row_incomplete;

        if (!message_already_shown && other_rows_with_incompletes > 0) {
          $('#edit-grid-container').prepend('<div class="messages warning">All items in the same config set will be updated simultaneously, therefore some items are automatically highlighted. Disassociation will occur on an individual basis and only checked items will be affected.</div>');
          message_already_shown = true;
        }
      });

      $('input#edit-submit-actions.form-submit').hide();
      $('#edit-select-actions').change(function () {
        val = $(this).val();

        if (val == 'reset' || val == 'delete') {
          lingotek_trigger_modal($('#' + val + '-link'));
        } else if (val == 'edit') {
          lingotek_trigger_modal($('#edit-settings-link'));
        } else if (val == 'workflow') {
          lingotek_trigger_modal($('#change-workflow-link'));
        } else {
          $('input#edit-submit-actions.form-submit').trigger('click');
        }
      });

      $('#edit-limit-select').change(function () {
        $('#edit-search-submit.form-submit').trigger('click');
      });
    }
  };

  function addClickToDownloadReady() {
    original_download_ready_URL = $('#download-ready').attr('href');
    $('#download-ready').click(function () {
      modifyActionButtonURL('#download-ready', original_download_ready_URL);
    });
  }
  function addClickToUploadButton() {
    original_upload_edited_URL = $('#upload-edited').attr('href');
    $('#upload-edited').click(function () {
      modifyActionButtonURL('#upload-edited', original_upload_edited_URL);
    });
  }
  this.check_box_count = 0;
  function addClickToCheckboxes(){
    $('#edit-grid-container .form-checkbox').each(function () {
      $(this).change(function (event) {
        clarifyButtonsForCheckboxes(event);
      });
    });
  }
  //changes the href associated with the download/upload buttons after they are clicked
  //but before the links are actually followed. Also checks to see if the results are 
  //filtered.
  function modifyActionButtonURL(element_id, original_URL) {
    var new_URL = original_URL.valueOf();//clones the original
    var entity_ids = getIDArray();
      var id_string = entity_ids.join(",");
      new_URL += entity_ids.length !== 0 ? "/" + entity_ids.join(",") : "";
      new_URL = entity_ids.length === 0 ? original_URL : new_URL;
      $(element_id).attr('href', new_URL);
  }
  //looks at every currently displayed row and pushes the entity_id of each
  //row with a checked checkbox into the return variable
  function getIDArray(visible_check) {
    var entity_ids = [];
    var visible = visible_check === true;
    $('#edit-grid-container .form-checkbox').each(function () {
      var val = $(this).val();
      if ($(this).attr('checked') || visible) {
        if (val !== 'on') {//'on' represents the 'select all' checkbox
          entity_ids.push(val);
        }
      }
    });
    return entity_ids;
  }
  function changeManageTabURL(){
    $('.active a').each(function (){
      if($(this).text() === 'Manage(active tab)'){
        $(this).attr('href', $('#refresh').attr('href'));
      }
    });
  }
  function clarifyButtonsForFilter(){
    var text = $('#clear-filters').text();
    $('.notify-checked-action').hide();
    $('#upload-edited').attr('title', 'Upload all pending source content');
    $('#download-ready').attr('title', 'Download complete translations');
    if(text === undefined || text === "") {
      $('.notify-filtered-action').hide();
    }
    else {
      $('.notify-filtered-action').show();
      $('#upload-edited').attr('title', 'Upload filtered results');
      $('#download-ready').attr('title', 'Download filtered results');
    }
  }
  function clarifyButtonsForCheckboxes(event){
    var box_checked = $(event.target).attr('checked');
    if($(event.target).val() === 'on' && box_checked) {
      this.check_box_count = $('#edit-grid-container .form-checkbox').length - 2; //accounts for the select all box
    }
    else if($(event.target).val() === 'on' && !box_checked) {
      this.check_box_count = 0;
    }
    else if(box_checked === true){
      this.check_box_count++;
    }
    else {
      this.check_box_count--;
    }
    if (this.check_box_count > 0) {
        $('.notify-filtered-action').hide();
        $('.notify-checked-action').show();
        $('#upload-edited').attr('title', 'Upload selected results');
        $('#download-ready').attr('title', 'Download selected results');
        return false;
    }
    else {
      clarifyButtonsForFilter();
    }
  }
  $(document).ready(function () {
    addClickToDownloadReady();
    addClickToUploadButton();
    addClickToCheckboxes();
    changeManageTabURL();
    clarifyButtonsForFilter();
  });
})(jQuery);
