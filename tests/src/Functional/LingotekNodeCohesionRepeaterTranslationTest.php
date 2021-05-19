<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\cohesion_elements\Entity\Component;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests translating a node with multiple locales including paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeCohesionRepeaterTranslationTest extends LingotekTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'content_moderation',
    'workflows',
    'node',
    'image',
    'comment',
    'cohesion_custom_styles',
    'cohesion_elements',
    'cohesion_website_settings',
    'cohesion_templates',
    'cohesion',
    'lingotek_cohesion_test',
  ];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_ES')
      ->save();
    ConfigurableLanguage::createFromLangcode('es-ar')
      ->setThirdPartySetting('lingotek', 'locale', 'es_AR')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    ContentLanguageSettings::loadByEntityTypeBundle('cohesion_layout', 'cohesion_layout')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);
    \Drupal::service('content_translation.manager')
      ->setEnabled('cohesion_layout', 'cohesion_layout', TRUE);

    $this->createCohesionField('node', 'article');
    $this->createCohesionComponent();

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Enable content moderation for articles.
    $workflow = $this->createEditorialWorkflow();
    $this->configureContentModeration('editorial', ['node' => ['article']]);

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'layout_canvas' => 1,
          ],
          'moderation' => [
            'upload_status' => 'published',
            'download_transition' => 'publish',
          ],
        ],
      ],
      'cohesion_layout' => [
        'cohesion_layout' => [
          'fields' => [
            'json_values' => 1,
          ],
        ],
      ],
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+cohesion-with-repeater');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithCohesionLayoutCanvasTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a site studio powered content.
    $this->drupalGet('node/add/article');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['layout_canvas[0][target_id][json_values]'] = <<<'JSON'
{"canvas":[{"uid":"cpt_myrepeater","type":"component","title":"MyRepeater","enabled":true,"category":"category-10","componentId":"cpt_myrepeater","componentType":"component-pattern-repeater","uuid":"004a5c2f-1b9e-44d5-a59d-da42e45d9a3d","parentUid":"root","isContainer":0,"children":[]}],"mapper":{},"model":{"004a5c2f-1b9e-44d5-a59d-da42e45d9a3d":{"settings":{"title":"MyRepeater"},"a2ec3944-3dc0-476d-b84d-a7974ddf6ceb":[{"ee4da1c5-e659-46c4-8a22-8969134fcfa7":"Llamas are very cool"},{"ee4da1c5-e659-46c4-8a22-8969134fcfa7":"Dogs are very cool"},{"ee4da1c5-e659-46c4-8a22-8969134fcfa7":"Cats are very cool"}]}},"previewModel":{"004a5c2f-1b9e-44d5-a59d-da42e45d9a3d":{}},"variableFields":{"004a5c2f-1b9e-44d5-a59d-da42e45d9a3d":[]},"meta":{"fieldHistory":[]}}
JSON;
    $edit['moderation_state[0][state]'] = 'published';

    // Mink does not "see" hidden elements, so we need to set the value of the
    // hidden element directly.
    $this->assertSession()
      ->elementExists('css', 'input[name="layout_canvas[0][target_id][json_values]"]')
      ->setValue($edit['layout_canvas[0][target_id][json_values]']);
    unset($edit['layout_canvas[0][target_id][json_values]']);

    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->node = Node::load(1);
    /** @var \Drupal\cohesion_elements\Entity\CohesionLayout $layout */
    $layout = CohesionLayout::load(1);
    $jsonValues = $layout->get('json_values')->value;
    $jsonValuesData = Json::decode($jsonValues);
    $modelUuid = $jsonValuesData['canvas'][0]['uuid'];
    $innerKeyUuid = 'ee4da1c5-e659-46c4-8a22-8969134fcfa7';
    $components = array_keys($jsonValuesData['model'][$modelUuid]);
    // The first element is settings, the second one is our component uuid.
    $componentUuid = $components[1];

    // Check that only the configured fields have been uploaded, including metatags.
    $data = Json::decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'));
    // As those uuids are generated, we don't know them in our document fake
    // responses. We need a token replacement system for fixing that.
    \Drupal::state()->set('lingotek.data_replacements', ['###MODEL_UUID###' => $modelUuid, '###COMPONENT_UUID###' => $componentUuid, '###INNER_KEY###' => $innerKeyUuid]);

    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertTrue(isset($data['layout_canvas'][0]['json_values'][$modelUuid]));
    $this->assertTrue(isset($data['layout_canvas'][0]['json_values'][$modelUuid][$componentUuid]));
    $this->assertCount(3, $data['layout_canvas'][0]['json_values'][$modelUuid][$componentUuid]);
    $this->assertEquals($data['layout_canvas'][0]['json_values'][$modelUuid][$componentUuid][0][$innerKeyUuid], "Llamas are very cool");
    $this->assertEquals($data['layout_canvas'][0]['json_values'][$modelUuid][$componentUuid][1][$innerKeyUuid], "Dogs are very cool");
    $this->assertEquals($data['layout_canvas'][0]['json_values'][$modelUuid][$componentUuid][2][$innerKeyUuid], "Cats are very cool");

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()
      ->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
    $this->assertText('Los perros son muy chulos');
    $this->assertText('Los gatos son muy chulos');

    /** @var \Drupal\Core\Entity\EntityStorageInterface $cohesionLayoutStorage */
    $cohesionLayoutStorage = $this->container->get('entity_type.manager')->getStorage('cohesion_layout');
    $cohesionLayoutStorage->resetCache([1]);

    /** @var \Drupal\cohesion_elements\Entity\CohesionLayout $layout */
    $layout = CohesionLayout::load(1);
    $layout = $layout->getTranslation('es-ar');
    $jsonValues = $layout->get('json_values')->value;
    $jsonValuesData = Json::decode($jsonValues);
    $this->assertEquals($jsonValuesData['model'][$modelUuid][$componentUuid][0][$innerKeyUuid], "Las llamas son muy chulas");
    $this->assertEquals($jsonValuesData['model'][$modelUuid][$componentUuid][1][$innerKeyUuid], "Los perros son muy chulos");
    $this->assertEquals($jsonValuesData['model'][$modelUuid][$componentUuid][2][$innerKeyUuid], "Los gatos son muy chulos");

    // The original content didn't change.
    $this->drupalGet('node/1');
    $this->assertText('Llamas are cool');
    $this->assertText('Llamas are very cool');
    $this->assertText('Dogs are very cool');
    $this->assertText('Cats are very cool');
  }

  protected function createCohesionField($entity_type_id, $bundle, $field_name = 'layout_canvas', $field_label = 'Layout canvas', $target_entity_type = 'cohesion_layout', $selection_handler = 'default', $selection_handler_settings = [], $cardinality = 1) {
    // Look for or add the specified field to the requested entity bundle.
    if (!FieldStorageConfig::loadByName($entity_type_id, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'type' => 'cohesion_entity_reference_revisions',
        'entity_type' => $entity_type_id,
        'cardinality' => $cardinality,
        'settings' => [
          'target_type' => $target_entity_type,
        ],
      ])->save();
    }
    if (!FieldConfig::loadByName($entity_type_id, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'bundle' => $bundle,
        'label' => $field_label,
        'settings' => [
          'handler' => $selection_handler,
          'handler_settings' => $selection_handler_settings,
        ],
      ])->save();
    }
    EntityFormDisplay::load("$entity_type_id.$bundle.default")
      ->setComponent($field_name, [
        'type' => 'cohesion_layout_builder_widget',
        'weight' => 1,
      ])
      ->save();
    EntityViewDisplay::load("$entity_type_id.$bundle.default")
      ->setComponent($field_name, [
        'label' => 'hidden',
        'type' => 'entity_reference_revisions_entity_view',
      ])
      ->save();
  }

  protected function createCohesionComponent() {
    $json = <<<'JSON'
{"canvas":[{"type":"container","uid":"list-container","title":"List container","status":{"collapsed":false},"uuid":"d7ff2afc-ba8a-4683-ad5c-fdae3495d7c9","parentUid":"root","isContainer":true,"children":[{"type":"container","uid":"component-pattern-repeater","title":"Pattern repeater","status":{"collapsed":false},"uuid":"9185b51b-f188-493f-9a11-dd1685b3de21","parentUid":"list-container","isContainer":true,"children":[{"type":"container","uid":"list-item","title":"List item","status":{"collapsed":false},"uuid":"1216657e-dd97-4d74-9845-9c3cd7511232","parentUid":"component-pattern-repeater","isContainer":true,"children":[{"type":"container","uid":"inline-element","title":"Inline element","status":{"collapsed":false},"uuid":"c273c296-e42b-4c46-b233-6ba4c68b97c2","parentUid":"list-item","isContainer":true,"children":[]}]}]}]}],"componentForm":[{"type":"form-field-container","uid":"form-field-repeater","title":"Field repeater","selected":false,"status":{"collapsed":false,"isopen":false},"options":{"formBuilder":true},"uuid":"a2ec3944-3dc0-476d-b84d-a7974ddf6ceb","parentUid":"root","isContainer":true,"children":[{"type":"form-field","uid":"form-input","title":"Input","translate":true,"status":{"collapsed":false},"uuid":"ee4da1c5-e659-46c4-8a22-8969134fcfa7","parentUid":"form-field-repeater","isContainer":false,"children":[]}]}],"mapper":{"d7ff2afc-ba8a-4683-ad5c-fdae3495d7c9":{"settings":{"formDefinition":[{"formKey":"list-container-settings","children":[{"formKey":"list-container-type","breakpoints":[],"activeFields":[{"name":"element","active":true}]},{"formKey":"list-container-style","breakpoints":[],"activeFields":[{"name":"customStyle","active":true},{"name":"customStyle","active":true}]}]}],"selectorType":"topLevel","form":null,"items":[]}},"9185b51b-f188-493f-9a11-dd1685b3de21":{"settings":{"formDefinition":[{"formKey":"component-pattern-repeater-settings","children":[{"formKey":"component-pattern-repeater-token","breakpoints":[],"activeFields":[{"name":"token","active":true}]}]}],"selectorType":"topLevel","form":null,"items":[]}},"1216657e-dd97-4d74-9845-9c3cd7511232":{"settings":{"formDefinition":[{"formKey":"list-item-settings","children":[{"formKey":"list-item-type","breakpoints":[],"activeFields":[{"name":"element","active":true}]},{"formKey":"list-item-style","breakpoints":[],"activeFields":[{"name":"customStyle","active":true},{"name":"customStyle","active":true}]}]}],"selectorType":"topLevel","form":null,"items":[]},"styles":{"formDefinition":[],"selectorType":"topLevel","items":[],"form":null}},"c273c296-e42b-4c46-b233-6ba4c68b97c2":{"settings":{"formDefinition":[{"formKey":"inline-element-settings","children":[{"formKey":"inline-element-text","breakpoints":[],"activeFields":[{"name":"content","active":true}]},{"formKey":"inline-element-markup","breakpoints":[],"activeFields":[{"name":"htmlMarkup","active":true},{"name":"htmlMarkupCustom","active":true},{"name":"helpText","active":true}]},{"formKey":"inline-element-style","breakpoints":[],"activeFields":[{"name":"customStyle","active":true},{"name":"customStyle","active":true}]}]}],"selectorType":"topLevel","form":null,"items":[]}}},"model":{"d7ff2afc-ba8a-4683-ad5c-fdae3495d7c9":{"settings":{"title":"List container","element":"ul","customStyle":[{"customStyle":""}],"settings":{"element":"ul","customStyle":[{"customStyle":""}]}},"context-visibility":{"contextVisibility":{"condition":"ALL"}},"styles":{"settings":{"element":"list-container"}}},"9185b51b-f188-493f-9a11-dd1685b3de21":{"settings":{"title":"Pattern repeater","token":"[field.a2ec3944-3dc0-476d-b84d-a7974ddf6ceb]"}},"1216657e-dd97-4d74-9845-9c3cd7511232":{"settings":{"title":"List item","element":"li","customStyle":[{"customStyle":""}],"settings":{"element":"li","customStyle":[{"customStyle":""}]}},"context-visibility":{"contextVisibility":{"condition":"ALL"}},"styles":{"settings":{"element":"list-item"}}},"c273c296-e42b-4c46-b233-6ba4c68b97c2":{"settings":{"title":"Inline element","customStyle":[{"customStyle":""}],"settings":{"htmlMarkup":"span","customStyle":[{"customStyle":""}]},"htmlMarkup":"span","content":"inline element [field.ee4da1c5-e659-46c4-8a22-8969134fcfa7]"},"context-visibility":{"contextVisibility":{"condition":"ALL"}},"styles":{"settings":{"element":"inline-element"}}},"a2ec3944-3dc0-476d-b84d-a7974ddf6ceb":{"settings":{"title":"Field repeater","type":"cohArray","componentField":true,"noTitle":true,"htmlClass":"coh-array--field-repeater","disableScrollbar":true,"addText":"Add","key":"field-repeater","sortable":true,"sortableOptions":{"axis":"y","handle":true},"schema":{"type":"array"},"machineName":"field-repeater","min":1,"max":100,"increment":1,"tooltipPlacement":"auto right"},"sortable":true,"sortableOptions":{"axis":"y","handle":true},"contextVisibility":{"condition":"ALL"}},"ee4da1c5-e659-46c4-8a22-8969134fcfa7":{"settings":{"title":"Input","schema":{"type":"string","escape":true},"machineName":"input","tooltipPlacement":"auto right"},"contextVisibility":{"condition":"ALL"}}},"previewModel":{"9185b51b-f188-493f-9a11-dd1685b3de21":{},"1216657e-dd97-4d74-9845-9c3cd7511232":{},"a2ec3944-3dc0-476d-b84d-a7974ddf6ceb":{},"ee4da1c5-e659-46c4-8a22-8969134fcfa7":{},"d7ff2afc-ba8a-4683-ad5c-fdae3495d7c9":{},"c273c296-e42b-4c46-b233-6ba4c68b97c2":{}},"variableFields":{"9185b51b-f188-493f-9a11-dd1685b3de21":[],"1216657e-dd97-4d74-9845-9c3cd7511232":[],"a2ec3944-3dc0-476d-b84d-a7974ddf6ceb":[],"ee4da1c5-e659-46c4-8a22-8969134fcfa7":[],"d7ff2afc-ba8a-4683-ad5c-fdae3495d7c9":[],"c273c296-e42b-4c46-b233-6ba4c68b97c2":[]},"meta":{"fieldHistory":[{"uuid":"ee4da1c5-e659-46c4-8a22-8969134fcfa7","type":"form-input","machineName":"input"},{"uuid":"a2ec3944-3dc0-476d-b84d-a7974ddf6ceb","type":"form-field-repeater","machineName":"field-repeater"}]}}
JSON;
    $component = Component::create([
      'id' => '3fedc674',
      'status' => TRUE,
      'label' => 'text',
      'json_values' => $json,
    ]);
    $component->save();
  }

}
