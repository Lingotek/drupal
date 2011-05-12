var lingotek;
if(!lingotek) lingotek = {};

/*
 * Global variables to keep track of the segments and xliff source:
 */
lingotek.community_translate_document_segments = new Array();
lingotek.community_translate_document_tuid = new Array();
lingotek.community_translate_languages = new Array();
lingotek.community_translate_counter = 0;

lingotek.xliff_result = "";
lingotek.xliff_source = "";
lingotek.source_language = "";
lingotek.targets = "";
lingotek.targetNode = new Array();
lingotek.firstRun = false;

/*
 * Returns the language code used by the Machine Translation engine when provided the format Lingotek uses.
 */
lingotek.parseLanguage = function(lang) {
  var l;
  if(lang.match("_")) {
    l = lang.toLowerCase().split("_");
  } else {
    l = lang.toLowerCase().split("-");
  }

  if(l[0] == "zh") {
    if(l[1] == "tw" || l[1] == "hk") {
      l[0] += "-TW";
    } else {
      l[0] += "-CN";
    }
  } else if(l[0] == "pt") {
    l[0] += "-PT";
  } else if(l[0] == "he") {
    l[0] = "iw";
  }

  return l[0];
}

/*
 * Prepare HTML to be embedded in XML
 */
lingotek.escapeForXml = function(text) {
  //return htmlspecialchars($text, $double_encode = FALSE);
  text = text.replace('&', '&amp;');
  text = text.replace('"', '&quot;');
  text = text.replace("'", '&apos;');
  text = text.replace('>', '&gt;');
  text = text.replace('<', '&lt;');

  return text;
}

/*
 * Google retry count
 */
lingotek.retry = {};
lingotek.retry["default"] = 5;

/*
 * Translation results from google (callback)
 */
lingotek.googleResult = function(json, counter) {
  var src = lingotek.community_translate_document_segments[counter];
  //Let's make sure before calling the callback function that it returned correctly
  if(json.responseStatus == 200 || lingotek.retry[counter] == 0) {
    var tuid = lingotek.community_translate_document_tuid[counter];
    var translations = json.responseData;

    lingotek.xliff_start_source(tuid, src);

    //Let's make sure at least one language returned successfully:
    if(json.responseData) {
      //If only one language is being translated:
      if(json.responseData.translatedText) {
        text = json.responseData.translatedText;
        lingotek.xliff_add_target(lingotek.community_translate_languages[0], text);
      }
      for(var i = 0; i < translations.length; i++) {
        var translation = translations[i];
        var text = "";
        if(translation.responseStatus == 200)
        {
          text = translation.responseData.translatedText;
          lingotek.xliff_add_target(lingotek.community_translate_languages[i], text);
        }
      }
    }

    lingotek.xliff_end_source();

    lingotek.community_translate_counter++;
    lingotek.progress(lingotek.community_translate_counter);
  }
  //Otherwise, let's try this again up to the default retry count:
  else {
    lingotek.retry[counter]--;
    setTimeout(function() {
      jQuery.getScript('http://ajax.googleapis.com/ajax/services/language/translate?v=1.0&q=' + encodeURIComponent(src) + lingotek.targets + "&callback=lingotek.googleResult_" + counter + "&prettyprint=true");
    }, 1000);
  }
}

/*
 * Submit translation to google
 */
lingotek.googleTranslate = function(text, src, targets, index) {
  var encodedText = encodeURIComponent(text);
  var langs = "";

  for(var i = 0; i < targets.length; i++) {
    var target = targets[i];
    langs += "&langpair=" + lingotek.parseLanguage(src) + "%7C" + lingotek.parseLanguage(target);
  }
  lingotek.targets = langs;

  //set retry count:
  lingotek.retry[index] = lingotek.retry["default"];
  //Prepare the callback:
  lingotek["googleResult_" + index] = function(json) {
    eval("var val = " + index + ";");
    lingotek.googleResult(json, val);
  }

  var url = 'http://ajax.googleapis.com/ajax/services/language/translate?v=1.0&q=' + encodedText + lingotek.targets + "&callback=lingotek.googleResult_" + index + "&prettyprint=true";
  if(url.length < 2000) {
    jQuery.getScript(url);
  }
  else {
    //The call won't work, so let's skip it.
    lingotek.community_translate_counter++;
    lingotek.progress(lingotek.community_translate_counter);
  }
}

/*
 * Translation results from microsoft (callback)
 */
lingotek.microsoftResult = function(result, counter) {
  var currentSegmentCount = Math.floor(counter / lingotek.community_translate_languages.length);
  var currentLanguage = counter % lingotek.community_translate_languages.length;

  var src = lingotek.community_translate_document_segments[currentSegmentCount];
  var tuid = lingotek.community_translate_document_tuid[currentSegmentCount];

  lingotek.xliff_prepend_target(lingotek.community_translate_languages[currentLanguage], lingotek.escapeForXml(result), src, tuid);

  lingotek.community_translate_counter++;
  var segmentCount = Math.floor(lingotek.community_translate_counter / lingotek.community_translate_languages.length);
  lingotek.progress(segmentCount);
}

/*
 * Submit translation to microsoft
 */
lingotek.microsoftTranslate = function(text, src, targets, index) {
  var encodedText = encodeURIComponent(text);
  sourceLanguage = lingotek.parseLanguage(src);

  for(var i = 0; i < targets.length; i++) {
    var targetLanguage = lingotek.parseLanguage(targets[i]);
    var function_counter = (index * targets.length) + i;
    lingotek.microsoftExecute(text, sourceLanguage, targetLanguage, function_counter);
  }
}

/*
 * Create the callback function for microsoft machine translation
 */
lingotek.microsoftExecute = function(text, sourceLanguage, targetLanguage, function_counter) {
  //Prepare the callback:
  lingotek["microsoftResult_" + function_counter] = function(json) {
    eval("var val = " + function_counter + ";");
    lingotek.microsoftResult(json, val);
  }

  try {
    if(jQuery.inArray(targetLanguage.substring(0,2), Microsoft.Translator.GetLanguages()) == -1) {
      throw "Target Language not supported: " + targetLanguage;
    }
    Microsoft.Translator.translate(text, sourceLanguage, targetLanguage, lingotek["microsoftResult_" + function_counter]);
  }
  catch(err) {
  	console.info(err);
    lingotek.community_translate_counter++;
    var segmentCount = Math.floor(lingotek.community_translate_counter / lingotek.community_translate_languages.length);
    lingotek.progress(segmentCount);
  }
}

/*
 * Generic translation method
 */
lingotek.translate = function(text, src, targets, index) {
  if(lingotek.mt_engine == 'google') {
    lingotek.googleTranslate(text, src, targets, index);
  }
  else if(lingotek.mt_engine == 'microsoft') {
    lingotek.microsoftTranslate(text, src, targets, index);
  }
}

/*
 * Update current progress
 */
lingotek.progress = function(current) {
  var percent = ( current / lingotek.segment_count) * 100;

  var progress_bar = jQuery("#lingotek_progress");
  var percent_rounded = Math.round(percent);

  progress_bar.html(percent_rounded + "%");
  progress_bar.attr("style", 'background-position: ' + percent_rounded + 'px 0px;');

  if(percent >= 100) {
    lingotek.finalize();
  }
}

/*
 * Initialization and execution of the machine translation
 */
lingotek.community_translate = function() {
	lingotek.jsIncludeCounter = 0;

  //Google Translation API:
  if(jQuery("[tag='lingotek-engine'][value='google']").length > 0) {
    jQuery.getScript("https://www.google.com/jsapi", lingotek.continue_when_ready);
  }

  //Microsoft Translation API:
  if(jQuery("[tag='lingotek-engine'][value='microsoft']").length > 0) {
    jQuery.getScript("http://api.microsofttranslator.com/V1/Ajax.svc/Embed?appId=No3DzjkuktHtOtTI_V5sUt2WCkefHlP4", lingotek.continue_when_ready);
  }

}

/*
 * Wait until the external javascript files are included before continuing
 */
lingotek.continue_when_ready = function() {
	lingotek.jsIncludeCounter++;
	if(lingotek.jsIncludeCounter >= 2) {
		lingotek.continue_now_ready();
	}
}

/*
 * Continue where Initialization and execution of the machine translation left off
 */
lingotek.continue_now_ready = function() {

  lingotek.mt_engine = jQuery("#lingotek").attr("engine");
  jQuery("#lingotek-mt-engine").val(jQuery("#lingotek").attr("engine"));
  var inputs = jQuery("#lingotek input");

  if(lingotek.mt_engine != "FALSE") {
    inputs.each(function(i, input) {
      lingotek.community_translate_languages.push(input.name);
      lingotek.firstRun = true;
    });
    lingotek.mt_run_languages();
  }

}

/*
 * Run machine translation on all currently added language
 */
lingotek.mt_run_languages = function() {
  if (lingotek.community_translate_languages.length > 0) {
    lingotek.mask();

    lingotek.xliff_source = unescape(jQuery("#lingotek_xliff").text());
    lingotek.source_language = lingotek.xliff_source.match(/source-language="([^"]*)"/)[1];

    lingotek.xliff_header();

    var matches = lingotek.xliff_source.match(/<source[^>]*>[^<]*<\/source>/g);
    var tuids = lingotek.xliff_source.match(/<trans-unit[^>]*id="[^"]*"/g);

    lingotek.segment_count = matches.length;

      if(matches) {
        for(var i = 0; i < matches.length; i++) {
          var text = matches[i].match(/<source[^>]*>([^<]*)<\/source>/)[1];
          var tuid = tuids[i].match(/<trans-unit[^>]*id="([^"]*)"/)[1];
        lingotek.community_translate_document_tuid.push(tuid);
        lingotek.community_translate_document_segments.push(text);
        lingotek.translate(text, lingotek.source_language, lingotek.community_translate_languages, i);
      }
    }
  }
}

/*
 * Prepare machine translation for running and run
 */
lingotek.mt_run = function() {
  lingotek.targetNode = new Array();
  lingotek.community_translate_languages = new Array();
  jQuery("[tag='lingotek-mt-checkbox']:checked").each(function(i, input) {
    var mt = jQuery(input);
    lingotek.targetNode.push(mt.attr("node"));
    lingotek.community_translate_languages.push(mt.attr("language"));
  });
  lingotek.mt_engine = jQuery("#lingotek-mt-engine").val();
  if(lingotek.targetNode.length > 0 && confirm(jQuery("#lingotek-warn-mt").text())) {
    lingotek.mt_run_languages();
  }
}

/*
 * Output a message to the user in the notification area (if locatable) or an alert box.
 */
lingotek.info_message = function(text) {
  messageArea = jQuery("#tabs-wrapper");
  if(messageArea.length > 0) {
    messageArea.after('<div class="messages success status">' + text + '</div>');
  }
  else {
    alert(text);
  }
}

/*
 * Call the Init function once the page has loaded
 */
jQuery(document).ready(lingotek.community_translate);


/*
 * Finish preparing the XLIFF for submission
 */
lingotek.finalize = function() {
  lingotek.xliff_footer();

  var ct = jQuery("#lingotek");
  var nid = ct.attr("node");
  var doc = ct.attr("doc");
  var engine = lingotek.mt_engine;

  var params = {
    "xliff" : lingotek.xliff_result,
    "docid" : doc,
    "engine" : engine
  };

  if(lingotek.targetNode.length > 0) {
    jQuery.extend(params, {"targetNode[]" : lingotek.targetNode});
  }

  jQuery.post("?q=lingotek/mt/" + nid, params, function(json) {
    //Reload when the process is done.
    if(json.status == 1) {
      alert(jQuery("#lingotek-error").html());
      location.reload(true);
    }
    else {
      location.reload(true);
    }
  }, 'json');
  lingotek.unmask();
  lingotek.info_message(jQuery("#lingotek-info").html());
}

//XLIFF Generation

/*
 * Generate xliff header
 */
lingotek.xliff_header = function() {
  var index = lingotek.xliff_source.match("<body>").index;
  lingotek.xliff_result = lingotek.xliff_source.substring(0, index) + "<body>";
}

/*
 * Start the xliff source language entry
 */
lingotek.xliff_start_source = function(tuid, original) {
  lingotek.xliff_result += '\n\t\t\t<trans-unit id="' + tuid + '">\n\t\t\t\t<source xml:lang="' + lingotek.source_language + '">' + lingotek.escapeForXml(original) + '</source>\n\t\t\t\t<alt-trans>\n';
}

/*
 * Append a target to a source language entry
 */
lingotek.xliff_add_target = function(language, result) {
  lingotek.xliff_result += '\t\t\t\t\t<target xml:lang="' + language + '">' + result + '</target>\n';
}

/*
 * Add a target to a source language entry (called out of order, so it looks it up)
 */
lingotek.xliff_prepend_target = function(language, result, src, tuid) {
  var regex = '<trans-unit id="' + tuid + '">\n\t\t\t\t<source xml:lang="' + lingotek.source_language + '">' + lingotek.escapeForXml(src) + '</source>\n\t\t\t\t<alt-trans>\n';
  if(lingotek.xliff_result.match(regex)) {
    var target = '\t\t\t\t\t<target xml:lang="' + language + '">' + result + '</target>\n';
    lingotek.xliff_result = lingotek.xliff_result.replace(regex, regex + target);
  }
  else {
    lingotek.xliff_start_source(tuid, src);
    lingotek.xliff_add_target(language, result);
    lingotek.xliff_end_source();
  }
}

/*
 * End a source entry
 */
lingotek.xliff_end_source = function() {
  lingotek.xliff_result += "\t\t\t\t</alt-trans>\n\t\t\t</trans-unit>\n\t\t";
}

/*
 * Append the footer to the xliff
 */
lingotek.xliff_footer = function() {
  var start = lingotek.xliff_source.match("</body>").index;
  lingotek.xliff_result += lingotek.xliff_source.substring(start);
}

//Masking

/*
 * Mask the whole page and add a notification area for % complete
 */
lingotek.mask = function() {
  jQuery("body").mask('<center>' + jQuery("#lingotek-loading").html() + '<p /><div class="lingotekProgress" id="lingotek_progress"></div><input type="button" value="' + jQuery("#lingotek-cancel").html() + '" onclick="lingotek.cancel();" /></center>', null);
}

/*
 * Unmask the page
 */
lingotek.unmask = function() {
	jQuery("#lingotek-mt-button").attr('disabled', 'disabled');
  jQuery("body").unmask();
}

/*
 * Cancel mask and MT
 */
lingotek.cancel = function() {
	lingotek.unmask();
	if(lingotek.firstRun) {
		jQuery.post("?q=lingotek/mt_cancel/" + jQuery("#lingotek").attr('node'), {}, function(json) { location.reload(true); });
	}
	else {
		location.reload(true);
	}
}

/*
 * Run synchronization on the checked target languages
 */
lingotek.update_checked = function() {
  lingotek.targetNode = new Array();
  jQuery("[tag='lingotek-mt-checkbox']:checked").each(function(i, input) {
    var mt = jQuery(input);
    lingotek.targetNode.push(mt.attr("node"));
  });
  if(lingotek.targetNode.length > 0 && confirm(jQuery("#lingotek-warn-sync").text())) {
    jQuery.post("?q=lingotek/download", {'targets[]' : lingotek.targetNode}, function(json) { location.reload(true); });
  }
}

/*
 * Run synchronization by node id
 */
lingotek.download = function(nid) {
  if(confirm(jQuery("#lingotek-warn-sync").text())) {
    jQuery.post("?q=lingotek/download/" + nid, {}, function(json) { location.reload(true); });
  }
}

/*
 * Select/deselect all targets in the table.
 */
lingotek.check_all = function(obj) {
  var checkboxes = jQuery("[tag='lingotek-mt-checkbox']");
  if(obj.checked) {
    checkboxes.attr("checked", "checked");
  }
  else {
    checkboxes.removeAttr("checked");
  }
}
