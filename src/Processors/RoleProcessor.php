<?php

namespace Configuration\Processors;

use Configuration\Configuration;

class RoleProcessor extends AbstractProcessor {

  static public function availableProcessors() {
    return array(
       // Converts the keys of an array from Role Ids to Role machine names.
      'RoleKeyId2Name',

       // Converts the values of an array from Role Ids to Role machine names.
      'RoleValueId2Name',

       // Converts the keys and the values of an array from Role Ids to Role machine names.
      'RoleAllId2Name',
    );
  }

  public function apply(Configuration $configuration, $properties = array()) {
    $roles_names = array(
      DRUPAL_ANONYMOUS_RID => 'anonymous_user',
      DRUPAL_AUTHENTICATED_RID => 'authenticated_user',
    );
    foreach (role_export_roles() as $role) {
      if ($role->rid > 2) {
        $roles_names[$role->rid] = $role->machine_name;
      }
    }

    $converted = array();
    foreach ($properties as $property) {
      $array = $configuration->getValue($property);

      foreach ($array as $key => $value) {
        switch ($this->getName()) {
          case 'RoleKeyId2Name':
            $converted[$roles_names[$key]] = $value;
            break;

          case 'RoleValueId2Name':
            $converted[$key] = $roles_names[$value];
            break;

          case 'RoleAllId2Name':
            $converted[$roles_names[$key]] = $roles_names[$value];
            break;
        }
      }
      $configuration->setValue($property, $converted);
    }
  }

  public function revert(Configuration $configuration, $properties = array()) {
    $roles_ids = array(
      'anonymous_user' => DRUPAL_ANONYMOUS_RID,
      'authenticated_user' => DRUPAL_AUTHENTICATED_RID,
    );
    foreach (role_export_roles() as $role) {
      if ($role->rid > 2) {
        $roles_ids[$role->machine_name] = $role->rid;
      }
    }

    $converted = array();
    foreach ($properties as $property) {
      $array = $configuration->getValue($property);

      foreach ($array as $key => $value) {
        switch ($this->getName()) {
          case 'RoleKeyId2Name':
            $converted[$roles_ids[$key]] = $value;
            break;

          case 'RoleValueId2Name':
            $converted[$key] = $roles_ids[$value];
            break;

          case 'RoleAllId2Name':
            $converted[$roles_ids[$key]] = $roles_ids[$value];
            break;
        }
      }
      $configuration->setValue($property, $converted);
    }
  }
}
