var lingotek = lingotek || {};
lingotek.forms = lingotek.forms || {};

(function ($) {

  lingotek.forms.init = function() {
    $("#edit-language")
      .change(function() {
        var language = $("#edit-language").val();
        var sourceLanguageSet = language != 'und';
        $('#lingotek_fieldset').drupalSetSummary(Drupal.t(sourceLanguageSet ? 'Enabled' : 'Disabled'));
        //Show all languages for MT Translation
        $('#lingotek_fieldset #edit-mttargets > div').show();
        if (sourceLanguageSet) {
          $('#edit-note').hide();
          $('#edit-content').show();
          $('#lingotek_fieldset .form-item-mtTargets-' + language).hide();
        }
        else {
          $('#edit-note').show();
          $('#edit-content').hide();
        }
      })
      .change();

    $("#edit-mtengine")
      .change(function() {
        var engine = $("#edit-mtengine").val();
        if (engine == '0') {
          $("#lingotek_fieldset .form-item-mtTargets").hide();
        }
        else {
          $("#lingotek_fieldset .form-item-mtTargets").show();
        }
      })
      .change();

    $('#edit-create-lingotek-document').change(function() {
      if (Drupal.settings.lingotek_no_document &&
        Drupal.settings.lingotek_has_entity_translations &&
        $(this).val() == 1 &&
        !lingotek.forms.new_document_alert_shown) {

        alert(Drupal.t('Warning: This node has @translation_count local translations. These translations will be removed when sending this node to Lingotek for the first time. If this is not your intention, please set the \'Push Node to Lingotek\' field to \'No\' before saving this form.', {'@translation_count': Drupal.settings.lingotek_has_entity_translations}));
        lingotek.forms.new_document_alert_shown = true;
      }
    });
  };

})(jQuery);

Drupal.behaviors.lingotekSetupStatus = {
  attach: lingotek.forms.init
}
