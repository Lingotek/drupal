(function ($, Drupal) {
  Drupal.behaviors.lingotekLocalesTableFilterByText = {
    attach: function attach(context, settings) {
      var $input = $('input.locales-filter-text').once('locales-filter-text');
      var $table = $($input.attr('data-table'));
      var $rows;

      function filterLocalesList(e) {
        var query = $(e.target).val().toLowerCase();

        function showViewRow(index, row) {
          var $row = $(row);
          var $sources = $row.find('*');
          var textMatch = $sources.text().toLowerCase().indexOf(query) !== -1;
          $row.closest('tr').toggle(textMatch);
        }

        if (query.length >= 2) {
          $rows.each(showViewRow);
        } else {
          $rows.show();
        }
      }

      if ($table.length) {
        $rows = $table.find('tbody tr');
        $input.on('keyup', filterLocalesList);
      }
    }
  };
})(jQuery, Drupal);
