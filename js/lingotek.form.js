/**
 * @file
 * Custom javascript.
 */
var lingotek = lingotek || {};
lingotek.forms = lingotek.forms || {};

(function ($) {
    
    // Page setup for node add/edit forms.
    lingotek.forms.init = function() {
        $("#edit-lingotek-language").change(updateVerticalTabSummary).change();
        $('#ltk-enable-from-et').bind('click',lingotek.forms.enableLtkFunc);
        $('#edit-lingotek-create-lingotek-document').change(updateVerticalTabSummary);
        $('#edit-lingotek-syncmethod').change(updateVerticalTabSummary);
        $('#edit-lingotek-profile').change(updateVerticalTabSummary);
        $('#edit-lingotek-lingotek-disabled').bind('click', updateVerticalTabSummary);
        updateVerticalTabSummary();
//        updateLingotekVisibilty(false);
    };
    
    lingotek.forms.enableLtkFromET = false;
    
    lingotek.forms.enableLtkFunc = function(){
        lingotek.forms.enableLtkFromET = true;
        $('#ltk-push-once').attr('default_value',1);
        $('#ltk-push-oncee').attr('value',1);
        $('#ltk-push-once').attr('checked','checked');
        updateVerticalTabSummary();
        return false;
    }
    
//    var updateLingotekVisibilty = function(element) {
//        var lingotekDis = $('#edit-lingotek-lingotek-disabled');
//        if(lingotekDis.attr('checked') ==  'checked' || lingotekDis.attr('checked') == '1') {
//            $('#edit-lingotek-content').hide();
//        } else if(element) { //the condition for element is to make sure this isn't the first run
//            $('#edit-lingotek-content').show();
//        }
//    }
    
    var updateVerticalTabSummary = function() {
        var isPushedToLingotek = !isNaN(parseInt($('#edit-lingotek-document-id').val()));
        var isEntityTranslationNode = $('#ltk-entity-translation-node').val();
        //console.log('pushedToLingotek: '+isPushedToLingotek);
        //console.log('entityTranslationNode: '+isEntityTranslationNode);
        var summaryMessages = [];
                
        if(!lingotek.forms.enableLtkFromET && isEntityTranslationNode && !isPushedToLingotek) {
            summaryMessages.push(Drupal.t("Entity Translation"));
            // hide form and show entity translation explanation and button
            $('#edit-lingotek-et-content').show();
            $('#edit-lingotek-lingotek-note').hide();
            $('.form-item-lingotek-lingotek-disabled').hide();
            $('.form-item-lingotek-profile').hide();
            $('#edit-lingotek-advanced').hide();
            $('#edit-lingotek-content').hide();
            $('#edit-lingotek-note').hide();
        }
        else {
            summaryMessages.push(Drupal.t("Lingotek"));
            // show form and hide entity translation explanation
            $('#edit-lingotek-et-content').hide();
            $('#edit-lingotek-content').show();
            $('#edit-lingotek-lingotek-note').show();
            $('.form-item-lingotek-lingotek-disabled').show();
            $('.form-item-lingotek-profile').show();
            
            var language = $("#edit-lingotek-language").val();
            var sourceLanguageSet = language != 'und'; 
            var autoUpload = $('#edit-lingotek-create-lingotek-document').is(":checked");
            var autoDownload = $('#edit-lingotek-syncmethod').is(":checked");
            
            summaryMessages.push( autoUpload ? Drupal.t("auto-upload") : Drupal.t("manual upload"));
            summaryMessages.push( autoDownload ? Drupal.t("auto-download") : Drupal.t("manual download"));
            
            // Source language set or not
            if (sourceLanguageSet) {
                $('#edit-lingotek-note').hide();
                $('#edit-lingotek-content').show();
                $('#edit-lingotek-lingotek-note').show();
                $('.form-item-lingotek-lingotek-disabled').show();
                $('.form-item-lingotek-profile').show();
            }
            else {
                //summaryMessages.push(Drupal.t("source language unset"));
                $('#edit-lingotek-note').show();
                $('#edit-lingotek-content').hide();
                $('#edit-lingotek-lingotek-note').hide();
                $('.form-item-lingotek-lingotek-disabled').hide();
                $('.form-item-lingotek-profile').hide();
            }
            
            var lingotekDis = $('#edit-lingotek-lingotek-disabled');
            if(lingotekDis.attr('checked') ==  'checked' || lingotekDis.attr('checked') == '1') {
              $('#edit-lingotek-content').hide();
              $('.form-item-lingotek-profile').hide();
            }
            
            if($('#edit-lingotek-profile').val() != '-1') {
              $('#edit-lingotek-content').hide();
              $('#edit-lingotek-advanced').hide();
            }
        }
        
        var extraDetails = summaryMessages.slice(1).join(', ');
        extraDetails = extraDetails.length ? '('+extraDetails+')' : extraDetails;
        $('#lingotek_fieldset').drupalSetSummary(summaryMessages.slice(0,1)+' '+extraDetails);
    };

})(jQuery);

Drupal.behaviors.lingotekSetupStatus = {
    attach: lingotek.forms.init
}
