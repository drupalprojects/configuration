<?php

namespace Configuration\Backends;

class Drupal7Backend implements BackendInterface {

  public function cache_clear_all($cid = NULL, $bin = NULL, $wildcard = FALSE) {
    return cache_clear_all($cid, $bin, $wildcard);
  }

  public function drupal_static_reset($function) {
    return drupal_static_reset($function);
  }

  public function entity_crud_get_info() {
    return entity_crud_get_info();
  }

  public function entity_get_info($entity_type) {
    return entity_get_info($entity_type);
  }

  public function entity_label($type, $entity) {
    entity_label($type, $entity);
  }

  public function entity_load_multiple_by_name($name, $reset) {
    return entity_load_multiple_by_name($name, $reset);
  }

  public function entity_load_single($type, $name) {
    return entity_load_single($type, $name);
  }

  public function field_create_field($field) {
    return field_create_field($field);
  }

  public function field_create_instance($field) {
    return field_create_instance($field);
  }

  public function field_delete_field($name) {
    return field_delete_field($name);
  }

  public function field_delete_instance($instance, $field_cleanup) {
    return field_delete_instance($instance, $field_cleanup);
  }

  public function field_info_cache_clear() {
    return field_info_cache_clear();
  }

  public function field_info_field($name) {
    return field_info_field($name);
  }

  public function field_info_fields() {
    return field_info_fields();
  }

  public function field_info_instance($entity_type, $field_name, $bundle) {
    return field_info_instance($entity_type, $field_name, $bundle);
  }

  public function field_info_instances() {
    return field_info_instances();
  }

  public function field_purge_batch($count) {
    return field_purge_batch($count);
  }

  public function field_update_field($field) {
    return field_update_field($field);
  }

  public function field_update_instance($field) {
    return field_update_instance($field);
  }

  public function filter_format_disable($format) {
    return filter_format_disable($format);
  }

  public function filter_format_load($name) {
    return filter_format_load($name);
  }

  public function filter_format_save($format) {
    return filter_format_save($format);
  }

  public function filter_formats() {
    return filter_formats();
  }

  public function filter_get_filters() {
    return filter_get_filters();
  }

  public function filter_list_format($name) {
    return filter_list_format($name);
  }

  public function filter_permission_name($format) {
    return filter_permission_name($format);
  }

  public function image_default_style_revert($name) {
    return image_default_style_revert($name);
  }

  public function image_effect_delete($effect) {
    return image_effect_delete($effect);
  }

  public function image_effect_save($effect) {
    return image_effect_save($effect);
  }

  public function image_style_delete($style) {
    return image_style_delete($style);
  }

  public function image_style_flush($style) {
    return image_style_flush($style);
  }

  public function image_style_load($name) {
    return image_style_load($name);
  }

  public function image_styles() {
    return image_styles();
  }

  public function language_list() {
    return language_list();
  }

  public function language_removeFromDatabase($langcode) {

    $languages = language_list();

    if (isset($languages[$langcode])) {
      // Remove translations first.
      db_delete('locales_target')
        ->condition('language', $langcode)
        ->execute();
      cache_clear_all('locale:' . $langcode, 'cache');
      // With no translations, this removes existing JavaScript translations file.
      _locale_rebuild_js($langcode);
      // Remove the language.
      db_delete('languages')
        ->condition('language', $langcode)
        ->execute();
      db_update('node')
        ->fields(array('language' => ''))
        ->condition('language', $langcode)
        ->execute();
      if ($languages[$langcode]->enabled) {
        variable_set('language_count', variable_get('language_count', 1) - 1);
      }
      module_invoke_all('multilingual_settings_changed');
    }

    // Changing the language settings impacts the interface:
    cache_clear_all('*', 'cache_page', TRUE);
  }

  public function language_writeToDatabase($language) {

    $current_language = db_select('languages')
      ->condition('language', $language->language)
      ->fields('languages')
      ->execute()
      ->fetchAssoc();

    // Set the default language when needed.
    $default = language_default();

    // Insert new language via api function.
    if (empty($current_language)) {
      locale_add_language($language->language,
                          $language->name,
                          $language->native,
                          $language->direction,
                          $language->domain,
                          $language->prefix,
                          $language->enabled,
                          ($language->language == $default->language));
      // Additional params, locale_add_language does not implement.
      db_update('languages')
        ->fields(array(
          'plurals' => empty($language->plurals) ? 0 : $language->plurals,
          'formula' => empty($language->formula) ? '' : $language->formula,
        ))
        ->condition('language', $language->language)
        ->execute();
    }
    // Update Existing language.
    else {

      $schema = drupal_get_schema('languages');
      $properties = array_keys($schema['fields']);

      // The javascript hash is not in the imported data but should be empty
      if (!isset($language->javascript)) {
        $language->javascript = '';
      }

      $fields = array_intersect_key((array) $language, array_flip($properties));
      db_update('languages')
        ->fields($fields)
        ->condition('language', $language->language)
        ->execute();

      // Set the default language when needed.
      $default = language_default();
      if ($default->language == $language->language) {
        variable_set('language_default', (object) $fields);
      }
    }
  }

  public function locale_add_language($langcode, $name = NULL, $native = NULL, $direction = LANGUAGE_LTR, $domain = '', $prefix = '', $enabled = TRUE, $default = FALSE) {
    return locale_add_language($langcode, $name, $native, $direction, $domain, $prefix, $enabled, $default);
  }

  public function locale_language_list($field = 'name', $all = FALSE) {
    return locale_language_list($field, $all);
  }

  public function locale_rebuild_js($langcode) {
    return locale_rebuild_js($langcode);
  }

  public function menu_delete($menu) {
    return menu_delete($menu);
  }

  public function menu_getIdentifiers() {
    $menus = db_query("SELECT menu_name, title FROM {menu_custom}")->fetchAll();
    $identifiers = array();
    foreach ($menus as $menu) {
      $identifiers[str_replace('-', '_', $menu->menu_name)] = $menu->title;
    }
    return $identifiers;
  }

  public function menu_load($name) {
    return menu_load($name);
  }

  public function menu_save($menu) {
    return menu_save($menu);
  }

  public function module_exists($module) {
    return module_exists($module);
  }

  public function module_invoke_all($hook) {
    return module_invoke_all($hook);
  }

  public function node_type_delete($name) {
    return node_type_delete($name);
  }

  public function node_type_get_names() {
    return node_type_get_names();
  }

  public function node_type_get_type($name) {
    return node_type_get_type($name);
  }

  public function node_type_save($node_type) {
    return node_type_save($node_type);
  }

  public function permission_deletePermission($permission) {
    return db_delete('role_permission')
      ->condition('permission', $permission)
      ->execute();
  }

  public function permission_savePermission($permission) {
    $fields = array();
    foreach ($permission['roles'] as $role) {
      $fields[] = array(
        'rid' => $this->roles_ids[$role],
        'permission' => $permission['permission'],
        'module' => $permission['module']
      );
    }

    if (!empty($fields)) {
      // Grant access only to the roles defined
      return db_insert('role_permission')
        ->fields($fields)
        ->execute();
    }
  }

  public function role_export_roles() {
    return role_export_roles();
  }

  public function role_roleExists($name) {
    return db_query("SELECT rid
                     FROM {role}
                     WHERE machine_name = :name",
                     array(':name' => $name))->fetchField();
  }

  public function user_permission_get_modules() {
    return user_permission_get_modules();
  }

  public function user_role_delete($role) {
    return user_role_delete($role);
  }

  public function user_role_permissions($roles) {
    return user_role_permissions($roles);
  }

  public function user_role_save($role) {
    return user_role_save($role);
  }

  public function variable_del($name) {
    return variable_del($name);
  }

  public function variable_get($name, $default = NULL) {
    return variable_get($name, $default);
  }

  public function variable_getIdentifiers() {
    return db_query("SELECT name, name
                     FROM {variable}
                     ORDER BY name ASC")->fetchAllKeyed();
  }

  public function variable_set($name, $value) {
    return variable_set($name, $value);
  }

  public function view_getIdentifiers() {
    return db_query("SELECT name, human_name
                     FROM {views_view}
                     ORDER BY name ASC")->fetchAllKeyed();
  }

  public function views_delete_view($view) {
    return views_delete_view($view);
  }

  public function views_get_view($name) {
    return views_get_view($name);
  }

  public function views_new_view() {
    return views_new_view();
  }

  public function views_save_view($view) {
    return views_save_view($view);
  }

  public function taxonomy_get_vocabularies() {
    return taxonomy_get_vocabularies();
  }

  public function taxonomy_vocabulary_machine_name_load($name) {
    return taxonomy_vocabulary_machine_name_load($name);
  }

  public function taxonomy_vocabulary_save($vocabulary) {
    return taxonomy_vocabulary_save($vocabulary);
  }

  public function taxonomy_vocabulary_delete($vid) {
    return taxonomy_vocabulary_delete($vid);
  }

  public function text_format_getFilterFormat($name) {
    return db_select('filter_format')
                    ->fields('filter_format')
                    ->condition('format', $name)
                    ->execute()
                    ->fetchObject();
  }

  public function wysiwyg_get_profile($name) {
    return wysiwyg_get_profile($name);
  }

  public function wysiwyg_profile_cache_clear() {
    return wysiwyg_profile_cache_clear();
  }

  public function wysiwyg_profile_delete($name) {
    return wysiwyg_profile_delete($name);
  }

  public function wysiwyg_profile_load_all() {
    return wysiwyg_profile_load_all();
  }

  public function wysiwyg_saveProfile($profile) {
    return db_merge('wysiwyg')
      ->key(array('format' => $profile->format))
      ->fields(array(
        'format' => $profile->format,
        'editor' => $profile->editor,
        'settings' => serialize($profile->settings),
      ))
      ->execute();
  }

}
