var lingotek;
if(!lingotek) lingotek = {};
lingotek.pm = {};

lingotek.pm.node = {};

lingotek.pm.init = function() {
  lingotek.pm.node.nid = jQuery("#lingotek_nid").val();
}

lingotek.pm.toggle_checkboxes = function(obj) {
  var checkboxes = jQuery("[tag='lingotek_pm_row']");
  if(obj.checked) {
    checkboxes.attr("checked", "checked");
  }
  else {
    checkboxes.removeAttr("checked");
  }
}

lingotek.pm.checked = function(callback) {
  lingotek.pm.checker = {};
  lingotek.pm.checker.target = new Array();
  lingotek.pm.checker.check = jQuery("[tag='lingotek_pm_row']:checked");
  lingotek.pm.checker.counter = 0;
  lingotek.pm.checker.callback = callback;

  lingotek.pm.checker.check.each(function(i, input) {
    lingotek.pm.checker.counter++;
    var target = jQuery(input);
    lingotek.pm.checker.target.push(target.attr("language"));
    if(lingotek.pm.checker.check.length == lingotek.pm.checker.counter) {
      lingotek.pm.checker.callback(lingotek.pm.checker.target);
      lingotek.pm.checker = {};
    }
  });
}

lingotek.pm.update = function() {
  lingotek.pm.checked(lingotek.pm.updateCallback);
}

lingotek.pm.updateCallback = function(targets) {
  jQuery.post("?q=lingotek/update/" + lingotek.pm.node.nid, {'targets[]' : targets}, function(json) { location.reload(true); });
}

lingotek.pm.mt = function() {
  lingotek.pm.checked(lingotek.pm.mtCallback);
}

lingotek.pm.mtCallback = function(targets) {
  jQuery.post("?q=lingotek/mt/" + lingotek.pm.node.nid, {'targets[]' : targets, 'engine' : $("#lingotek-mt-engine").val()}, function(json) { location.reload(true); });
}

jQuery(document).ready(lingotek.pm.init);
