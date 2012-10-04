<?php

/**
 * @file
 * Provide customizations for some components of the configuration UI.
 */

/**
 * Theme function for displaying form buttons
 */
function theme_configuration_ui_form_buttons(&$vars) {
  $output = drupal_render_children($vars['element']);
  return !empty($output) ? "<div class='buttons clear-block'>{$output}</div>" : '';
}
