(function ($) {
  Drupal.behaviors.configuration_ui = {
    attach: function(context, settings) {
      // Configuration management form
      $('#configuration-ui-tracking-form span.config-status:not(.processed)').each(function() {
        $(this).addClass('processed');

        // Check the overridden status of each configuration
        var id = $(this).attr('rel');
        $(this).load('/admin/config/configuration/view/' + id + '/status');
      });
    }
  }
})(jQuery);
