<?php

namespace Configuration\Backends;

interface BackendInterface {

  public function cache_clear_all($cid = NULL, $bin = NULL, $wildcard = FALSE);

  public function drupal_static_reset($function);

  public function entity_crud_get_info();

  public function entity_label($type, $entity);

  public function entity_get_info($entity_type);

  public function entity_load_multiple_by_name($name, $reset);

  public function entity_load_single($type, $name);

  public function field_create_field($field);

  public function field_create_instance($field);

  public function field_delete_field($name);

  public function field_delete_instance($instance, $field_cleanup);

  public function field_info_cache_clear();

  public function field_info_field($name);

  public function field_info_fields();

  public function field_info_instance($entity_type, $field_name, $bundle);

  public function field_info_instances();

  public function field_purge_batch($count);

  public function field_update_field($field);

  public function field_update_instance($field);

  public function filter_format_disable($format);

  public function filter_format_load($name);

  public function filter_format_save($format);

  public function filter_formats();

  public function filter_get_filters();

  public function filter_list_format($name);

  public function filter_permission_name($format);

  public function image_default_style_revert($name);

  public function image_effect_delete($effect);

  public function image_effect_save($effect);

  public function image_style_delete($style);

  public function image_style_flush($style);

  public function image_style_load($name);

  public function image_styles();

  public function language_list();

  public function language_removeFromDatabase($language);

  public function language_writeToDatabase($language);

  public function locale_add_language($langcode, $name = NULL, $native = NULL, $direction = LANGUAGE_LTR, $domain = '', $prefix = '', $enabled = TRUE, $default = FALSE);

  public function locale_language_list($field = 'name', $all = FALSE);

  public function locale_rebuild_js($langcode);

  public function menu_delete($menu);

  public function menu_getIdentifiers();

  public function menu_load($name);

  public function menu_save($menu);

  public function module_exists($module);

  public function module_invoke_all($hook);

  public function node_type_delete($name);

  public function node_type_get_names();

  public function node_type_get_type($name);

  public function node_type_save($node_type);

  public function permission_deletePermission($permission);

  public function permission_savePermission($permission);

  public function role_export_roles();

  public function role_roleExists($name);

  public function user_permission_get_modules();

  public function user_role_delete($role);

  public function user_role_permissions($roles);

  public function user_role_save($role);

  public function variable_del($name);

  public function variable_get($name);

  public function variable_getIdentifiers();

  public function variable_set($name, $value);

  public function views_delete_view($view);

  public function views_get_view($name);

  public function view_getIdentifiers();

  public function views_new_view();

  public function views_save_view($view);

  public function taxonomy_get_vocabularies();

  public function taxonomy_vocabulary_machine_name_load($name);

  public function taxonomy_vocabulary_save($vocabulary);

  public function taxonomy_vocabulary_delete($vid);

  public function text_format_getFilterFormat($name);

  public function wysiwyg_get_profile($name);

  public function wysiwyg_profile_load_all();

  public function wysiwyg_profile_cache_clear();

  public function wysiwyg_profile_delete($name);

  public function wysiwyg_saveProfile($profile);
}
