<?php
/**
 * Theme function for displaying form buttons
 */
function theme_configuration_form_buttons(&$vars) {
  $output = drupal_render_children($vars['element']);
  return !empty($output) ? "<div class='buttons clear-block'>{$output}</div>" : '';
}