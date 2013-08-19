<?php

/**
 * A handler to link to the Lingotek Workbench.
 *
 * Definition terms:
 *   - output formats: An array where the first entry is displayed on boolean true
 *      and the second is displayed on boolean false. An example for sticky is:
 *      @code
 *      'output formats' => array(
 *        'sticky' => array(t('Sticky'), ''),
 *      ),
 *      @endcode
 *
 * @ingroup views_field_handlers
 */
class lingotek_views_handler_workbench_link extends views_handler_field {
  /**
   * Constructor; calls to base object constructor.
   */
  function construct() {
    parent::construct();
  }
  
  /**
   * Implements views_object#option_definition().
   */
  function option_definition() {
    $options = parent::option_definition();
    $options['link_text'] = array('default' => 'Edit Translation');
    return $options;
  }
  
  /**
   * Implements views_handler#options_form().
   */
  function options_form(&$form, &$form_state) {
    $form['link_text'] = array(
      '#type' => 'textfield',
      '#title' => 'Text to display',
      '#default_value' => $this->options['link_text'],
    );
  }

  function render($values) {
    global $language;
    
    $entity_type = $this->view->base_table;
    
    if($entity_type == 'node') {
      $entity = entity_load_single($entity_type, $values->nid);
    } elseif($entity_type == 'comment') {
      $entity = entity_load_single($entity_type, $values->cid);
    }
        
    if($language->language == $entity->language) {
      return;
    }
    
    drupal_add_css(drupal_get_path('module', 'lingotek') . '/style/workbench-link.css', array('group' => CSS_DEFAULT));

    $text = '<img src="' . base_path() . drupal_get_path('module', 'lingotek') . '/images/ico_chevs_dd.png" >'; 
    $url = 'node/' . $values->nid . '/lingotekworkbench/' . Lingotek::convertDrupal2Lingotek($language->language);
    
    return l($text, $url, array('attributes' => array('target' => '_blank', 'title' => 'Edit this translation'), 'html' => TRUE));
  }

  function element_type($none_supported = FALSE, $default_empty = FALSE, $inline = FALSE) {
    if ($inline) {
      return 'span';
    }

    if (isset($this->definition['element type'])) {
      return $this->definition['element type'];
    }

    return 'div';
  }
}