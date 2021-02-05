
  var hash = window.location.hash;
  if (hash.length && hash.indexOf("access_token") !== -1) {
    let regex = /^access_token=(\w{8}-\w{4}-\w{4}-\w{4}-\w{12})/
    let access_token = regex.exec(hash.substr(1));
    if (access_token && access_token[1]) {
      fetch(window.location, {
        method: 'POST',
        body: JSON.stringify({
           access_token: access_token[1],
          })
      }).then(function (response) {
        if (response.ok) {
          return response.json();
        } else {
          return Promise.reject(response);
        }
      }).then(function (data) {
        // This is the JSON from our response
        console.log(data);
        window.location.href = window.location.origin + window.location.pathname + window.location.search;
      }).catch(function (err) {
        // There was an error
        console.warn('Something went wrong.', err);
        window.location.href = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
      });
    }
  }
