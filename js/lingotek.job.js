(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.lingotekJobId = {
    attach: function attach(context, settings) {
      var $context = $(context);

      function jobIdHandler(e) {
        var data = e.data;
        var options = data.options;
        var baseValue = $(e.target).val();

        var rx = new RegExp(options.replace_pattern, 'g');
        var transliteration = baseValue.toLowerCase().replace(rx, options.replace);
        $(options.element).val(transliteration);
      }
      Object.keys(settings.lingotekJobId).forEach(function (elementId) {
        var options = settings.lingotekJobId[elementId];

        var $element = $context.find(elementId).once('job-id');

        var eventData = {
          element: $element,
          options: options
        };

        if ($element.val() === '') {
          $element.on('formUpdated.jobId', eventData, jobIdHandler).trigger('formUpdated.jobId');
        }
      });
    },
    transliterate: function transliterate(element, settings) {
      var rx = new RegExp(options.replace_pattern, 'g');
      var transliteration = element.toLowerCase().replace(rx, options.replace);
      return transliteration;
    }
  };
})(jQuery, Drupal, drupalSettings);