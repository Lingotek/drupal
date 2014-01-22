<?php

/**
 * @file
 * Lingotek Module + Rules Module Integration
 */

/**
 * Implements hook_rules_action_info().
 */
function lingotek_rules_action_info() {
  $actions['lingotek_entity_upload'] = array(
    'group' => t('Lingotek'),
    'label' => t('Upload an entity'),
    'base' => 'lingotek_rules_entity_upload',
    'parameter' => array(
      'entity' => array(
        'type' => 'entity',
        'label' => t('Entity'),
      ),
    ),
  );
  $actions['lingotek_entity_download'] = array(
    'group' => t('Lingotek'),
    'label' => t('Download Translations'),
    'base' => 'lingotek_rules_entity_download',
    'parameter' => array(
      'entity' => array(
        'type' => 'entity',
        'label' => t('Entity'),
      ),
      'language' => array(
        'type' => 'list<text>',
        'label' => t('Language'),
        'options list' => 'lingotek_rules_languages',
      ),
    ),
  );
  $actions['lingotek_entity_change_profile'] = array(
    'group' => t('Lingotek'),
    'label' => t('Change Profile'),
    'base' => 'lingotek_rules_entity_change_profile',
    'parameter' => array(
      'entity' => array(
        'type' => 'entity',
        'label' => t('Entity'),
      ),
      'language' => array(
        'type' => 'text',
        'label' => t('Profile'),
        'options list' => 'lingotek_get_profile_options',
      ),
    ),
  );
  return $actions;
}

function lingotek_rules_languages() {
  return Lingotek::getLanguages('language');
}

function lingotek_rules_entity_upload($wrapper, $settings, $state) {
  $entity_type = $wrapper->type();
  $id = $wrapper->getIdentifier();
  $entity = lingotek_entity_load_single($entity_type, $id);
  lingotek_entity_upload($entity, $entity_type);
}

function lingotek_rules_entity_download($wrapper, $languages, $settings, $state) {
  $entity_type = $wrapper->type();
  $id = $wrapper->getIdentifier();
  $entity = $wrapper->value();
  foreach ($languages as $lingotek_locale) {
    lingotek_entity_download($entity, $entity_type, $lingotek_locale);
  }
}

function lingotek_rules_entity_change_profile($wrapper, $profile, $settings, $state) {
  $entity_type = $wrapper->type();
  $id = $wrapper->getIdentifier();
  $entity = $wrapper->value();
  $entity->lingotek['profile'] = $profile;
  lingotek_entity_save($entity, $entity_type);
}


/**
 * Implements hook_rules_condition_info().
 */
function lingotek_rules_condition_info() {
  $items = array();
  $items['lingotek_entity_all_downloaded'] = array(
    'group' => t('Lingotek'),
    'module' => 'lingotek',
    'label' => t('Entity has all translations downloaded'),
    'parameter' => array(
      'entity' => array(
        'type' => 'entity',
        'label' => t('Entity'),
        'desciption' => t('Specifies the entity for which the translation was downloaded.'),
      ),
    ),
  );
  $items['lingotek_entity_has_profile'] = array(
    'group' => t('Lingotek'),
    'module' => 'lingotek',
    'label' => t('Entity has profile'),
    'parameter' => array(
      'entity' => array(
        'type' => 'entity',
        'label' => t('Entity'),
        'desciption' => t('Specifies the entity for which the translation was downloaded.'),
      ),
      'profile' => array(
        'type' => 'token',
        'label' => t('Profile'),
        'description' => t('The Lingotek profile assigned to this entity'),
        'options list' => 'lingotek_get_profiles_by_name',
        'restriction' => 'input',
      ),
    ),
  );
  return $items;
}

function lingotek_entity_all_downloaded($entity) {
  $info = $entity->info(); // this $entity comes in as a DrupalEntityWrapper object
  $entity_type = $info['type'];
  $query = db_select('lingotek_entity_metadata', 'lem')
      ->fields('lem', array('entity_id'))
      ->condition('lem.entity_id', $entity->getIdentifier())
      ->condition('lem.entity_type', $entity_type)
      ->condition('lem.entity_key', 'target_sync_status_%', 'LIKE')
      ->condition('lem.value', LingotekSync::STATUS_CURRENT, '!=');
  $result = $query->execute();
  if ($result->rowCount()) {
    return FALSE;
  }
  return TRUE;
}

function lingotek_entity_has_profile($entity, $profile) {
  $raw = $entity->raw(); // this $entity comes in as a DrupalEntityWrapper object
  $entity_profile_id = isset($raw->lingotek['profile']) ? $raw->lingotek['profile'] : "NONE";
  return $entity_profile_id === $profile;
}

/**
 * Implements hook_rules_event_info().
 */
function lingotek_rules_event_info() {

  $defaults = array(
    'group' => t('Lingotek'),
    'module' => 'lingotek',
  );
  return array(
    'lingotek_pre_upload' => $defaults + array(
      'label' => t('Before an entity is uploaded'),
      'variables' => array(
        'entity' => array('type' => 'entity', 'label' => t('entity uploaded')),
      ),
    ),
    'lingotek_post_upload' => $defaults + array(
      'label' => t('After an entity was uploaded'),
      'variables' => array(
        'entity' => array('type' => 'entity', 'label' => t('entity uploaded')),
      ),
    ),
    'lingotek_pre_download' => $defaults + array(
      'label' => t('Before an entity is downloaded'),
      'variables' => array(
        'entity' => array('type' => 'entity', 'label' => t('entity downloaded')),
      ),
    ),
    'lingotek_post_download' => $defaults + array(
      'label' => t('After an entity was downloaded'),
      'variables' => array(
        'entity' => array('type' => 'entity', 'label' => t('entity downloaded')),
      ),
    ),
    'lingotek_entity_ready' => $defaults + array(
      'label' => t('A translation is ready for download'),
      'variables' => array(
        'entity' => array('type' => 'entity', 'label' => t('entity ready for download')),
      ),
    ),
    'lingotek_entity_all_ready' => $defaults + array(
      'label' => t('All translations are ready for download'),
      'variables' => array(
        'entity' => array('type' => 'entity', 'label' => t('entity ready for download')),
      ),
    ),
  );
}

