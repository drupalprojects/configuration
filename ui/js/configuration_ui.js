(function ($) {
  Drupal.behaviors.configuration_ui = {
    attach: function(context, settings) {
      // Configuration management form
      $('#configuration-ui-tracking-form span.config-status:not(.processed)').each(function() {
        $(this).addClass('processed');

        // Check the overridden status of each configuration
        var id = $(this).attr('rel');
        $(this).load(Drupal.settings.basePath + 'admin/config/configuration/view/' + id + '/status', updateCheckedCount);
      });
      $('#configuration-ui-tracking-form').addClass('status-check-completed');

      $("fieldset.configuration .form-checkbox").bind('click', function() {
        var current_checkbox = $(this);
        var include_dependencies = $("input[name='include_dependencies']:checked").length;
        var include_optionals = $("input[name='include_optionals']:checked").length;
        if (include_optionals || include_dependencies) {
          var url = 'dependencies_optionals';
          if (!include_optionals) {
            url = 'dependencies';
          }
          if (!include_dependencies) {
            url = 'optionals';
          }
          var original_value = current_checkbox.parents('td').next().html();
          current_checkbox.parents('td').next().html(original_value + ' ' + Drupal.t('(Finding dependencies...)'));
          $.getJSON(Drupal.settings.basePath + 'admin/config/configuration/view/' + $(this).val() + '/' + url, function(data) {

            $.each(data, function(index, array) {
              if (current_checkbox.is(':checked')) {
                $("input[value='" + array + "']").attr("checked", "checked");
              }
              else{
                $("input[value='" + array + "']").attr("checked", "");
              }

            });
            current_checkbox.parents('td').next().html(original_value);
            updateCheckedCount(context);
          });
        }
      });

      updateCheckedCount(context);
    }
  }

  Drupal.behaviors.configurationFieldsetSummaries = {
    attach: updateCheckedCount
  }

  function updateCheckedCount(context) {
    var needs_attention = 0;
    var in_sync = Drupal.t('In Sync');
    var processing = Drupal.t('Processing...');
    $("fieldset.configuration").each(function(){
      var id = '#' + this.id;
      $(this, context).drupalSetSummary(function (context) {
        var count = $(id + ' table .form-item :input[type="checkbox"]:checked').length;

        needs_attention = 0;
        $('.status-check-completed #' + id + ' table span.processed').each(function(){
          if (this.innerHTML != in_sync && this.innerHTML != processing) {
            needs_attention++;
          }
        });

        if (needs_attention > 0) {
          return Drupal.t('@count selected <strong>(@attention needs attention)</strong>', {'@count': count, '@attention': needs_attention});
        }
        else {
          return Drupal.t('@count selected', {'@count': count});
        }
      });
    });
  }

})(jQuery);
