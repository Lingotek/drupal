
  var hash = window.location.hash;
  if (hash.length && hash.indexOf("access_token") !== -1) {
    let regex = /^access_token=(\w{8}-\w{4}-\w{4}-\w{4}-\w{12})/
    let access_token = regex.exec(hash.substr(1));
    if (access_token && access_token[1]) {
      document.cookie = 'lingotek_access_token=' + access_token[1] + ';path=/';
    }
    window.location.href = window.location.origin + window.location.pathname + window.location.search;
  } else {
    window.location.href = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
  }
