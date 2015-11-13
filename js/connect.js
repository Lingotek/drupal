/**
 * Created by rob on 8/22/14.
 */

  var hash = window.location.hash;
  if (hash.length && hash.indexOf("access_token") !== -1) {
    var parts = hash.substring(1).split('=');
    var access_token = parts[1];
    var url_with_access_token = window.location.origin + window.location.pathname + window.location.search + '&access_token=' + access_token;
    window.location.href = url_with_access_token;
  } else {
    window.location.href = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
  }
