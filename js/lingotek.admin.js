/**
 * @file
 * Custom javascript.
 */

(function ($) {

Drupal.behaviors.lingotekAdminForm = {
  attach: function (context) {

    $('fieldset.lingotek-account', context).drupalSetSummary(function (context) {
      return Drupal.t($('#connection_summary').val() + '<br>' + $('#account_summary').val());
    });
    
//    $('fieldset.lingotek-connection-status', context).drupalSetSummary(function (context) {
//      return Drupal.t();
//    });
    
    $('fieldset.lingotek-translate-content', context).drupalSetSummary(function (context) {
      $list = [];
      $('#edit-node-translation input').each(function( index ) {
        if($(this).attr('id').substring(0, 9) == 'edit-type' && $(this).attr('checked') == '1') {
          $list.push($(this).val());
        }
      });
      if($list.length == 0) {
        return '<span style="color:red;">' + Drupal.t('Disabled') + '</span>';
      } else {
        return '<span style="color:green;">' + Drupal.t('Enabled') + '</span> ' + $list.length + ' ' + Drupal.t('content types');
      }
    });
    
    $('fieldset.lingotek-translate-comments', context).drupalSetSummary(function (context) {
      $list = [];
      $('#edit-lingotek-translate-comments-node-types input').each(function( index ) {
        if($(this).attr('checked') == '1') {
          $list.push($(this).val());
        }
      });
      if($list.length == 0) {
        return '<span style="color:red;">' + Drupal.t('Disabled') + '</span>';
      } else {
        return '<span style="color:green;">' + Drupal.t('Enabled') + ' </span> ' + $list.length + ' ' + Drupal.t('content types');
      }
    });

    $('fieldset.lingotek-translate-configuration', context).drupalSetSummary(function (context) {
      $list = [];
      $('#edit-additional-translation input').each(function( index ) {
        if($(this).attr('checked') == '1') {
          name = $(this).attr('name');
          name = name.substring(name.lastIndexOf('_') + 1, name.length);
          $list.push(name);
        }
      });
      if($list.length == 0) {
        return '<span style="color:red;">' + Drupal.t('Disabled') + '</span>';
      } else {
        return '<span style="color:green;">' + Drupal.t('Enabled') + '</span>: ' + $list.join(', ');
      }
    });
    
    $('fieldset.lingotek-preferences', context).drupalSetSummary(function (context) {
      $list = [];
      $('#edit-region').each(function( index ) {
        if($(this).attr('checked') == '1') {
          $list.push($(this).val());
        }
      });
      return Drupal.t($list.join(', '));
    });
  }
};

})(jQuery);
