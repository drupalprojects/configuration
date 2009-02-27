<?php

/**
 * This function is used to register the actions this config supports. These actions can be used
 * later on with an #action identifier to act on a specific part of the config dataset. 
 */
function block_configuration_actions() {
  return array(
    'block_add_block_form' => array(
      '#description' => t('Add block'),
      '#summary' => 'block_configuration_summaries',
      '#parameters' => array('module'),
      '#success map' => array(
        '/delta' => array('#create' => true, '#value php' => '$value = db_result(db_query("SELECT delta FROM {blocks} bl INNER JOIN {boxes} bo ON bl.delta = bo.bid AND bl.module = \"block\" WHERE info = \"%s\"", $context->parent->item["info"]));')
      ),
      '#identifiers' => array('delta')
    ),
    'block_admin_display_form' => array(
      '#description' => t('Set/Change the region or order of a block'),
      '#summary' => 'block_configuration_summaries',
      '#parameters' => array('@blocks1', 'theme'),
      '#build map' => array(
        '/[module][delta]' => array('#required' => true),
        '/region' => array('#default' => 'left'),
        '/[status=0]/region' => array('#value' => -1),
        '/theme' => array('#default callback' => array('variable_get' => array('theme_default', 'garland'))),
        // The blocks1 parameter is needed to make sure all default values are retained
        '/@blocks1' => array('#create' => true, '#value php' => '$value = _block_rehash();'),
        '/@blocks2' => array('#create' => true, '#value php' => '$parent = &$context->parent;' ."\n".
            '$value = array($parent->item["module"] ."_". $parent->item["delta"] => $parent->item);'),
      
      ),
      '#build data' => '@blocks2',
      '#identifiers' => array('delta')
    ),
    'block_admin_configure' => array(
      '#description' => t('Change configurations for a custom block'),
      '#summary' => 'block_configuration_summaries',
      '#parameters' => array('module', 'delta'),
      '#build map' => array(
        '/info[module=block][delta]' => array('#default php' => '$value = db_result(db_query("SELECT bo.info FROM {blocks} bl INNER JOIN {boxes} bo ON bl.delta = bo.bid AND bl.module = \"block\" WHERE bl.delta = \"%d\"", $context->parent->item["delta"]));')
      ),
      '#identifiers' => array('delta')
    ),
    'block_box_delete' => array(
      '#description' => t('Delete a custom block'),
      '#summary' => 'block_configuration_summaries',
      '#parameters' => array('delta'),
      '#identifiers' => array('delta')
    )
  );
}

/**
 * This hook is used to return the configuration maps that describes how to configure
 * this module given the supplied dataset
 * 
 * // TODO Look into setting up 'initial', 'before', and 'after' maps
 */
function block_configuration_maps() {
  return array(
    // /block is used to catch all block actions an ensure they can be used with this component
    '/block' => array( 
      '#include' => drupal_get_path('module', 'block') .'/block.admin.inc',
      '#action callback' => 'block_configuration_action'
    ),
    '/block/action' => array(
      '#attribute' => true
    ),
    '/block[id]/module' => array(
      '#create' => true,
      '#value php' => '$split = explode("-", $context->parent->item["id"]);' ."\n".
          '$value = $split[0];'
    ),
    '/block[id]/delta' => array(
      '#create' => true,
      '#value php' => '$split = explode("-", $context->parent->item["id"]);' ."\n".
          '$value = $split[1];'
    ),
    '/block/id' => array(
      '#delete' => true
    ),
    '/block/info' => array(
      '#alias' => array('description')
    ),
    '/block[@action=delete]/module' => array(
      '#default' => 'block'
    ),
    '/block[@action=delete]/module=block' => array(
      '#required' => true
    ),
    '/block[not(module)][not(delta)]/info' => array(
      '#required' => true
    ),
    '/block[not(delta)][info]/module' => array(
      '#default' => 'block'
    ),
    '/block/pages' => array(
      '#value php' => 'if (is_string($value)) return;'. "\n".
                        '$value = implode("\r\n", $value);' ."\n".
                        '$value = str_replace("[front]", "<front>", $value);'
    )
  );
}

/**
 * Map actions callback to determine which actions need executing for this data
 */
function block_configuration_action(&$data, &$context) {
  // If only "info" was supplied for an existing block,
  // get the delta so we don't try and make a new one
  if ($data['module'] == 'block' && !$data['delta'] && $data['info']) {
    if ($delta = db_result(db_query('SELECT delta FROM {blocks} bl INNER JOIN {boxes} bo ON bl.delta = bo.bid AND bl.module = "block" WHERE info = "%s"', $data['info']))) {
      $data['delta'] = $delta;
    }
  }
  if ($context->action == 'delete') {
    return 'block_box_delete';
  }
  else if ($data['module'] == 'block' && !$data['delta'] && $data['info']) {
    return array('block_add_block_form', 'block_admin_display_form');
  }
  else {
    return array('block_admin_configure', 'block_admin_display_form');
  }
}