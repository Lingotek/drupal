/**
 * @file
 * Custom javascript.
 */

(function ($) {

Drupal.behaviors.lingotekAdminForm = {
  attach: function (context) {

    //when a content type checkbox is clicked
    $('td:first-child .form-checkbox', context).click( function() {
      isChecked = $(this).attr('checked');
      $(this).parents('tr').find('.form-checkbox').each( function() {
        if(isChecked) {
          $(this).attr('checked', isChecked);
        } else {
          $(this).removeAttr('checked');
        }
      })
    });
    
    //when a field checkbox is clicked
    $('.field.form-checkbox', context).click( function() {
      if($(this).attr("name") == "lingotek_module_translation_from_drupal") {
        return;
      }
      
      row = $(this).parents('tr')
      if($(this).attr('checked')) {
        row.find('td:first-child .form-checkbox').each( function() {
          $(this).attr('checked', true);
        })
      } else {
        count = 0;
        row.find('.field.form-checkbox').each( function() {
          count += $(this).attr('checked') ? 1 : 0;
        })
        if(count == 0) {
          row.find('td:first-child .form-checkbox').attr('checked',false);
        }
      }
    });

    //ensure that there is a vertical tab set
    if($('.vertical-tabs').length != 0) {
      $('fieldset.lingotek-account', context).drupalSetSummary(function (context) {
        return Drupal.t($('#account_summary').val() + ' / ' + $('#connection_summary').val());
      });

      $('fieldset.lingotek-translate-content', context).drupalSetSummary(function (context) {
        $list = [];
        total = 0;
        $('#edit-node-translation input').each(function( index ) {
          var id = $(this).attr('id');
          if(id && id.substring(0, 9) == 'edit-type') {
            if($(this).attr('checked') ==  'checked' || $(this).attr('checked') == '1') {
              $list.push($(this).val());
            }
            total++;
          }
        });
        if($list.length == 0) {
          return '<span style="color:red;">' + Drupal.t('Disabled') + '</span>';
        } else {
          return '<span style="color:green;">' + Drupal.t('Enabled') + '</span>: ' + $list.length + '/' + total + ' ' + Drupal.t('content types');
        }
      });

      $('fieldset.lingotek-translate-comments', context).drupalSetSummary(function (context) {
        $list = [];
        total = 0;
        $('#edit-lingotek-translate-comments-node-types input').each(function( index ) {
          if($(this).attr('checked') ==  'checked' || $(this).attr('checked') == '1') {
            $list.push($(this).val());
          }
          total++;
        });
        if($list.length == 0) {
          return '<span style="color:red;">' + Drupal.t('Disabled') + '</span>';
        } else {
          return '<span style="color:green;">' + Drupal.t('Enabled') + '</span>: ' + $list.length + '/' + total + ' ' + Drupal.t('content types');
        }
      });

      $('fieldset.lingotek-translate-configuration', context).drupalSetSummary(function (context) {
        $list = [];
        max = 5;
        extra_text = "";
        $('#edit-additional-translation input').each(function( index ) {
          if($(this).attr('checked') ==  'checked' || $(this).attr('checked') == '1') {
            name = $(this).attr('name');
            
            if(name.indexOf("config") != -1){
                name = name.substring(name.lastIndexOf('_') + 1, name.length - 1);
                $list.push(name);
            }
            else {
                extra_text = " +&nbsp;community";
            }
            
          }
        });
        if ($list.length === 0 && extra_text.length === 0) {
            return '<span style="color:red;">' + Drupal.t('Disabled') + '</span>';
        } else if ($list.length === max) {
            return '<span style="color:green;">' + Drupal.t('Enabled') + '</span>: all' + extra_text;
        } else {
            return '<span style="color:green;">' + Drupal.t('Enabled') + '</span>: ' + $list.join(', ') + extra_text;
        }
      });

      $('fieldset.lingotek-preferences', context).drupalSetSummary(function (context) {
        $list = [];
        $('#edit-region').each(function( index ) {
          if($(this).attr('checked') ==  'checked' || $(this).attr('checked') == '1') {
            $list.push($(this).val());
          }
        });
        return Drupal.t($list.join(', '));
      });
    }
  }
};

})(jQuery);
