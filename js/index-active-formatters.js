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

        // Stage, remove the last _status].
        var $stage = $parts[2].slice(0, -1);

        // Other checkboxes for same processor.
        var $other = $('input.search-api-processor-status-' + $name).not(this);

        // Weight row.
        var $row = $("select[name='processors[" + $name + "][" + $stage + "][weight]']", context).closest('tr');

        // Settings tab.
        var $tab = $('.search-api-processor-settings-' + $name, context).data('verticalTab');

        // The tabledrag table.
        var $stageFieldset = $(this).closest('fieldset');

        // Bind a click handler to this checkbox to conditionally show and hide
        // the filter's table row and vertical tab pane.
        $checkbox.on('click.searchApiUpdate', function () {
          if ($checkbox.is(':checked')) {
            $('#edit-' + $stage + '-order').show();
            $('#edit-' + $stage + ' .tabledrag-toggle-weight-wrapper').show();
            $row.show();
            if ($tab) {
              $tab.tabShow().updateSummary();
            }
            // By default processors are enabled in all stages, they can be
            // disabled manually if only one stage is actually desired.
            if ($other.length) {
              $other.prop('checked', true);
              $other.triggerHandler('click.searchApiUpdate');
            }
          }
          else {
            var $enabled_processors = $stageFieldset.find('input.form-checkbox:checked').length;

            if (!$enabled_processors) {
              $('#edit-' + $stage + '-order').hide();
              $('#edit-' + $stage + ' .tabledrag-toggle-weight-wrapper').hide();
            }

            $row.hide();
            if ($tab && !$other.is(':checked')) {
              $tab.tabHide().updateSummary();
            }
          }
          // Re-stripe the table after toggling visibility of table row.
          Drupal.tableDrag['edit-' + $stage.replace('_', '-') + '-order'].restripeTable();
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
