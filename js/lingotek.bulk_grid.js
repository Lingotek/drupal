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
    $('#edit-grid-container .form-checkbox').each(function() {
      if($(this).attr('checked')) {
        val = $(this).val();
        if(val != 'on') {
          entity_ids.push(val);
        }
      }
    });
    if(entity_ids.length > 0) {
      $('#edit-select-actions').val('select');
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

  var message_already_shown = false;

  Drupal.behaviors.lingotekBulkGrid = {
    attach: function (context) {
      $('.form-checkbox').change(function() {
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
      $('#edit-select-actions').change(function() {
        val = $(this).val();

        if(val == 'reset' || val == 'delete') {
          lingotek_trigger_modal($('#'+val+'-link'));
        } else if(val == 'edit') {
          lingotek_trigger_modal($('#edit-settings-link'));
        } else if(val == 'workflow') {
          lingotek_trigger_modal($('#change-workflow-link'));
        } else  {
          $('input#edit-submit-actions.form-submit').trigger('click');
        }
      });

      $('#edit-limit-select').change(function() {
        $('#edit-search-submit.form-submit').trigger('click');
      });
  }
};
 //the whole concept behind this function is kinda hacky, I'm pulling the data
    //straight from the html instead of getting data from the backend, I considered
    //pulling from the database and filtering in the PHP, but there's no guarantee
    //the results would match the frontend's filters
  function scrapeAllFilteredPages(element_id, original_URL) {
    var entity_ids = getIDArray(true);//gets the displayed data
    var requestCount = 0;
    //cancel the default link href, otherwise it tries to follow the link before
    //the ajax calls finish
    $(element_id).removeAttr('href');
    //get the data for every page option on the table
    $('.pager li.pager-item a').each(function(){
     var href = $(this).attr('href');
      $.ajax({
          url: href,
          dataType: 'text',
          success: function(data) {
            //here we're grabbing the inputs inside the table
            var begin = data.indexOf("<table");
            var end = data.indexOf("</table>") + "</table>".length;
            var tableData = data.substring(begin, end);
            var el = $('<div></div>');
            el.html(tableData);
            $('input', el).each(function(){
                entity_ids.push($(this).val());
            });    
          },
          complete: function(){
              requestCount++;
              //after the last page is processed, execute the new URL with a POST
              //request
              if(requestCount === $('.pager li.pager-item a').length){
                clickFilteredURL(entity_ids, original_URL, element_id);
              }
          }
      });       
    });
    //covers the case of only one page of results
    if($('.pager li.pager-item a').length === 0 && entity_ids.length > 0){
        clickFilteredURL(entity_ids, original_URL, element_id);
    }
    
  }
  function clickFilteredURL(entity_ids, original_URL){
    //the entity_type is passed as a URL param
    //the entities to upload/download are sent in a POST body to deal with URL 
    //length restraints
    var new_URL = original_URL.valueOf();
    new_URL += entity_ids.length !== 0 ? "/" + entity_ids.join(",") : "";
      var form = $('<form>', {
        'action': original_URL + "?render=overlay",
        'method': "post"
      }).append($('<input>', {
        'name': 'comma_separated_ids',
        'value': entity_ids.join(","),
        'type' : 'hidden'
      }));
      form.append();
      form.submit();
      form.remove();
  }
  
  function addClickToDownloadReady() {
    original_download_ready_URL = $('#download-ready').attr('href');
    $('#download-ready').click(function(){modifyActionButtonURL('#download-ready', original_download_ready_URL);});
  }
  function addClickToUploadButton() {
    original_upload_edited_URL = $('#upload-edited').attr('href');
    $('#upload-edited').click(function(){modifyActionButtonURL('#upload-edited', original_upload_edited_URL);});
  }
  function refreshStatuses(){
    $('#refresh')[0].click();
  }
  //changes the href associated with the download/upload buttons after they are clicked
  //but before the links are actually followed. Also checks to see if the results are 
  //filtered.
  function modifyActionButtonURL(element_id, original_URL) {
    var new_URL = original_URL.valueOf();//clones the original
    var entity_ids = getIDArray();
    var filterValue = $('#clear-filters').text();
    console.log(filterValue);
    var filterOn = filterValue !== "";
    if(filterOn === true && entity_ids.length === 0) {
      scrapeAllFilteredPages(element_id, original_URL);
    }
    else {
      var id_string = entity_ids.join(",");
      new_URL += entity_ids.length !== 0 ? "/" + entity_ids.join(",") : "";
      new_URL = entity_ids.length === 0 ? original_URL : new_URL;
      $(element_id).attr('href', new_URL);
    }
  }
  //looks at every currently displayed row and pushes the entity_id of each
  //row with a checked checkbox into the return variable
  function getIDArray(visible_check) {
    var entity_ids = [];
    var visible = visible_check === true;
    $('#edit-grid-container .form-checkbox').each(function() {
        var val = $(this).val();
        if($(this).attr('checked') || visible) {
          if(val !== 'on') {//'on' represents the 'select all' checkbox
            entity_ids.push(val);
          }
        }
    });
    return entity_ids;
  }
  $(document).ready(function(){
    addClickToDownloadReady();
    addClickToUploadButton();
  });
})(jQuery);
