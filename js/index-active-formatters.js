/**
 * @file
 * Attaches show/hide functionality to processor checkboxes in "Filters" tabs.
 */

(function ($) {

  "use strict";

  Drupal.behaviors.searchApiIndexFormatter = {
    attach: function (context, settings) {
      $('.search-api-status-wrapper input.form-checkbox', context).each(function () {
        var $checkbox = $(this);
        var $parts = $checkbox.attr('name').split('[');

        // Name, remove the last ].
        var $name = $parts[1].slice(0, -1);

        // Weight row(s).
        var $rows = $("select.search-api-processor-weight-" + $name, context);
        // .closest('tr');

        // Settings tab.
        var $tab = $('.search-api-processor-settings-' + $name, context).data('verticalTab');

        // Bind a click handler to this checkbox to conditionally show and hide
        // the filter's table row and vertical tab pane.
        $checkbox.on('click.searchApiUpdate', function () {
          if ($checkbox.is(':checked')) {
            $rows.each( function( index, row ) {
              $(row).closest('tr').show();
            });
            if ($tab) {
              $tab.tabShow().updateSummary();
            }
          }
          else {
            $rows.each( function( index, row ) {
              $(row).closest('tr').hide();
            });
            if ($tab) {
              $tab.tabHide().updateSummary();
            }
          }
        });

        // Attach summary for configurable items (only for screen-readers).
        if ($tab) {
          $tab.details.drupalSetSummary(function () {
            return $checkbox.is(':checked') ? Drupal.t('Enabled') : Drupal.t('Disabled');
          });
        }

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler('click.searchApiUpdate');
      });
    }
  };

})(jQuery);
