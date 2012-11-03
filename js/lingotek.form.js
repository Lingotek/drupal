var lingotek = lingotek || {};
lingotek.forms = lingotek.forms || {};

(function ($) {

  // Page setup for node add/edit forms.
  lingotek.forms.init = function() {
    $("#edit-language")
      .change(function() {
        var language = $("#edit-language").val();
        var sourceLanguageSet = language != 'und';
        $('#lingotek_fieldset').drupalSetSummary(Drupal.t(sourceLanguageSet ? 'Enabled' : 'Disabled'));
        if (sourceLanguageSet) {
          $('#edit-note').hide();
          $('#edit-content').show();
        }
        else {
          $('#edit-note').show();
          $('#edit-content').hide();
        }
      })
      .change();
  };

})(jQuery);

Drupal.behaviors.lingotekSetupStatus = {
  attach: lingotek.forms.init
}
