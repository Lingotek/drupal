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
class LingotekNodeCohesionTranslationTest extends LingotekTestBase {

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
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+cohesion');
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
{"canvas":[{"uid":"3fedc674","type":"component","title":"Text","enabled":true,"category":"category-3","componentId":"3fedc674","componentType":"container","uuid":"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8","parentUid":"root","isContainer":0,"children":[]}],"mapper":{},"model":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{"settings":{"title":"Text: Llamas are very cool"},"6b671446-cb09-46cb-b84a-7366da00be36":{"text":"<p>Llamas are very cool</p>\n","textFormat":"cohesion"},"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d":{"name":"White","uid":"white","value":{"hex":"#ffffff","rgba":"rgba(255, 255, 255, 1)"},"wysiwyg":true,"class":".coh-color-white","variable":"$coh-color-white","inuse":true,"link":true},"e6f07bf5-1bfa-4fef-8baa-62abb3016788":"coh-style-max-width---narrow","165f1de9-336c-42cc-bed2-28ef036ec7e3":"coh-style-padding-bottom---large","4c27d36c-a473-47ec-8d43-3b9696d45d74":""}},"previewModel":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":{}},"variableFields":{"ac9583af-74f9-419d-9f8a-68f6ca0ef5e8":[]},"meta":{"fieldHistory":[]}}
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
    $components = array_keys($jsonValuesData['model'][$modelUuid]);
    // The first element is settings, the second one is our component uuid.
    $componentUuid = $components[1];

    // Check that only the configured fields have been uploaded, including metatags.
    $data = Json::decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'));
    // As those uuids are generated, we don't know them in our document fake
    // responses. We need a token replacement system for fixing that.
    \Drupal::state()->set('lingotek.data_replacements', ['###MODEL_UUID###' => $modelUuid, '###COMPONENT_UUID###' => $componentUuid]);

    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertTrue(isset($data['layout_canvas'][0]['json_values'][$modelUuid]));
    $this->assertTrue(isset($data['layout_canvas'][0]['json_values'][$modelUuid][$componentUuid]));
    $this->assertEquals($data['layout_canvas'][0]['json_values'][$modelUuid][$componentUuid], '<p>Llamas are very cool</p>' . PHP_EOL . '');

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
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');

    /** @var \Drupal\Core\Entity\EntityStorageInterface $cohesionLayoutStorage */
    $cohesionLayoutStorage = $this->container->get('entity_type.manager')->getStorage('cohesion_layout');
    $cohesionLayoutStorage->resetCache([1]);

    /** @var \Drupal\cohesion_elements\Entity\CohesionLayout $layout */
    $layout = CohesionLayout::load(1);
    $layout = $layout->getTranslation('es-ar');
    $jsonValues = $layout->get('json_values')->value;
    $jsonValuesData = Json::decode($jsonValues);
    $textValue = $jsonValuesData['model'][$modelUuid][$componentUuid]['text'];
    $this->assertEquals($textValue, '&lt;p&gt;Las llamas son muy chulas&lt;/p&gt;' . PHP_EOL . '');

    // The original content didn't change.
    $this->drupalGet('node/1');
    $this->assertText('Llamas are cool');
    $this->assertText('Llamas are very cool');
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
{"canvas":[{"type":"container","uid":"container","title":"Container","status":{"collapsed":false},"parentIndex":1,"uuid":"67b320ff-88ba-4f35-ab41-73981a3d4043","parentUid":"root","isContainer":true,"children":[{"type":"container","uid":"container","title":"Container","status":{"collapsed":false},"parentIndex":1,"uuid":"0782b4fc-ce52-4494-9d39-788ed7e8fa4a","parentUid":"container","isContainer":true,"children":[{"type":"item","uid":"wysiwyg","title":"WYSIWYG","status":{"collapsed":true},"parentIndex":1,"uuid":"c6857d35-9264-4a99-b76a-66f4cec46e0d","parentUid":"container","isContainer":false,"children":[]}]}]}],"componentForm":[{"type":"form-container","uid":"form-tab-container","title":"Tab container","parentIndex":"form-layout","status":{"collapsed":false},"options":{"formBuilder":true},"parentUid":"root","uuid":"59d4f7c1-9bf0-416f-aed2-1c0d3dc53db3","isContainer":true,"children":[{"type":"form-container","uid":"form-tab-item","title":"Tab item","parentIndex":"form-layout","status":{"collapsed":false},"options":{"formBuilder":true},"parentUid":"form-tab-container","uuid":"90394ace-9a36-4aed-a27e-f116e53c5ef1","isContainer":true,"children":[{"type":"form-container","uid":"form-section","title":"Field group","parentIndex":"form-layout","status":{"collapsed":false},"options":{"formBuilder":true},"uuid":"de5a8dd6-fa58-4eb9-89db-26bf1e8fb68a","parentUid":"form-tab-item","isContainer":true,"children":[{"type":"form-field","uid":"form-wysiwyg","title":"WYSIWYG","parentIndex":"form-fields","status":{"collapsed":false,"collapsedParents":[]},"uuid":"6b671446-cb09-46cb-b84a-7366da00be36","parentUid":"form-section","isContainer":false,"children":[]}]}]},{"type":"form-container","uid":"form-tab-item","title":"Tab item","parentIndex":"form-layout","status":{"collapsed":false},"options":{"formBuilder":true},"parentUid":"form-tab-container","uuid":"c51b5bde-ce05-455d-8b35-9791c2488cb2","isContainer":true,"children":[{"type":"form-container","uid":"form-section","title":"Field group","parentIndex":"form-layout","status":{"collapsed":true},"options":{"formBuilder":true},"parentUid":"form-tab-item","uuid":"27de5221-720a-4382-b68a-fc70fccc01ac","isContainer":true,"children":[{"type":"form-field","uid":"form-colorpicker","title":"Color picker","parentIndex":"form-fields","status":{"collapsed":false,"collapsedParents":["27de5221-720a-4382-b68a-fc70fccc01ac"]},"uuid":"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d","parentUid":"form-section","isContainer":false,"children":[]}]},{"type":"form-container","uid":"form-section","title":"Field group","parentIndex":"form-layout","status":{"collapsed":true},"options":{"formBuilder":true},"uuid":"fc0305e5-93bb-4fa4-b49d-11e51928d4a2","parentUid":"form-tab-item","isContainer":true,"children":[{"type":"form-field","uid":"form-select","title":"Select","parentIndex":"form-fields","status":{"collapsed":false,"collapsedParents":["fc0305e5-93bb-4fa4-b49d-11e51928d4a2"]},"uuid":"e6f07bf5-1bfa-4fef-8baa-62abb3016788","parentUid":"form-section","isContainer":false,"children":[]}]},{"type":"form-container","uid":"form-section","title":"Field group","parentIndex":"form-layout","status":{"collapsed":true},"options":{"formBuilder":true},"uuid":"ca81f1fd-f274-499b-b14f-b513d9468689","parentUid":"form-tab-item","isContainer":true,"children":[{"type":"form-field","uid":"form-select","title":"Select","parentIndex":"form-fields","status":{"collapsed":false,"collapsedParents":["ca81f1fd-f274-499b-b14f-b513d9468689"]},"uuid":"165f1de9-336c-42cc-bed2-28ef036ec7e3","parentUid":"form-section","isContainer":false,"children":[]}]},{"type":"form-container","uid":"form-section","title":"Field group","parentIndex":"form-layout","status":{"collapsed":true},"options":{"formBuilder":true},"parentUid":"form-tab-item","uuid":"99c8fcb7-faf5-41ca-9307-b15d767ee7c1","isContainer":true,"children":[{"type":"form-field","uid":"form-select","title":"Select","parentIndex":"form-fields","status":{"collapsed":false,"collapsedParents":["99c8fcb7-faf5-41ca-9307-b15d767ee7c1"]},"parentUid":"form-section","uuid":"4c27d36c-a473-47ec-8d43-3b9696d45d74","isContainer":false,"children":[]}]}]},{"type":"form-container","uid":"form-tab-item","title":"Tab item","parentIndex":"form-layout","status":{"collapsed":false},"options":{"formBuilder":true},"parentUid":"form-tab-container","uuid":"2170c5e2-f3e6-4b0f-a378-9629ec9ebfa2","isContainer":true,"children":[{"type":"form-container","uid":"form-section","title":"Field group","parentIndex":"form-layout","status":{"collapsed":false},"options":{"formBuilder":true},"uuid":"cfb80dbb-f6c6-4ae3-8083-5ec5f35da7df","parentUid":"form-tab-item","isContainer":true,"children":[{"type":"form-help","uid":"form-helptext","title":"Help text","parentIndex":"form-help","status":{"collapsed":false,"collapsedParents":[]},"uuid":"63e45257-9c1d-45d5-8af1-7b356df559ac","parentUid":"form-section","isContainer":false,"children":[]},{"type":"form-help","uid":"form-helptext","title":"Help text","parentIndex":"form-help","status":{"collapsed":false,"collapsedParents":[]},"uuid":"35539d06-7709-4d75-babc-9fc4a4e3657a","parentUid":"form-section","isContainer":false,"children":[]}]}]}]}],"mapper":{"67b320ff-88ba-4f35-ab41-73981a3d4043":{"settings":{"selectorType":"topLevel","formDefinition":[{"formKey":"container-settings","children":[{"formKey":"container-width","breakpoints":[],"activeFields":[{"name":"width","active":true}]},{"formKey":"common-link-animation","breakpoints":[{"name":"xl"}],"activeFields":[{"name":"linkAnimation","active":true},{"name":"animationType","active":true},{"name":"animationScope","active":true},{"name":"animationParent","active":true},{"name":"animationTarget","active":true},{"name":"animationScale","active":true},{"name":"animationDirection","active":true},{"name":"animationDirection","active":true},{"name":"animationDirection","active":true},{"name":"animationDistance","active":true},{"name":"animationPieces","active":true},{"name":"animationOrigin","active":true},{"name":"animationFoldHeight","active":true},{"name":"animationHorizontalFirst","active":true},{"name":"animationIterations","active":true},{"name":"animationEasing","active":true},{"name":"animationDuration","active":true}]},{"formKey":"container-style","breakpoints":[],"activeFields":[{"name":"customStyle","active":true},{"name":"customStyle","active":true}]},{"formKey":"common-link-modifier","breakpoints":[],"activeFields":[{"name":"modifier","active":true},{"name":"modifierType","active":true},{"name":"interactionScope","active":true},{"name":"interactionParent","active":true},{"name":"interactionTarget","active":true},{"name":"modifierName","active":true}]}]}],"form":null},"styles":{"items":[],"formDefinition":[{"formKey":"background","children":[{"formKey":"background-color","breakpoints":[{"name":"xl"}],"activeFields":[{"name":"background-color","active":true}]}]}],"selectorType":"topLevel","form":null},"markup":{"title":"Markup","selectorType":"topLevel","formDefinition":[{"formKey":"tab-markup-classes-and-ids","children":[{"formKey":"tab-markup-add-classes","breakpoints":[],"activeFields":[{"name":"classes","active":true}]}]}],"form":null}},"0782b4fc-ce52-4494-9d39-788ed7e8fa4a":{"settings":{"selectorType":"topLevel","formDefinition":[{"formKey":"container-settings","children":[{"formKey":"container-width","breakpoints":[],"activeFields":[{"name":"width","active":true}]},{"formKey":"common-link-animation","breakpoints":[{"name":"xl"}],"activeFields":[{"name":"linkAnimation","active":true},{"name":"animationType","active":true},{"name":"animationScope","active":true},{"name":"animationParent","active":true},{"name":"animationTarget","active":true},{"name":"animationScale","active":true},{"name":"animationDirection","active":true},{"name":"animationDirection","active":true},{"name":"animationDirection","active":true},{"name":"animationDistance","active":true},{"name":"animationPieces","active":true},{"name":"animationOrigin","active":true},{"name":"animationFoldHeight","active":true},{"name":"animationHorizontalFirst","active":true},{"name":"animationIterations","active":true},{"name":"animationEasing","active":true},{"name":"animationDuration","active":true}]},{"formKey":"container-style","breakpoints":[],"activeFields":[{"name":"customStyle","active":true},{"name":"customStyle","active":true}]},{"formKey":"common-link-modifier","breakpoints":[],"activeFields":[{"name":"modifier","active":true},{"name":"modifierType","active":true},{"name":"interactionScope","active":true},{"name":"interactionParent","active":true},{"name":"interactionTarget","active":true},{"name":"modifierName","active":true}]}]}],"form":null},"styles":{"items":[],"formDefinition":[],"selectorType":"topLevel","form":null},"markup":{"title":"Markup","selectorType":"topLevel","formDefinition":[{"formKey":"tab-markup-classes-and-ids","children":[{"formKey":"tab-markup-add-classes","breakpoints":[],"activeFields":[{"name":"classes","active":true}]}]}],"form":null}},"c6857d35-9264-4a99-b76a-66f4cec46e0d":{"settings":{"selectorType":"topLevel","formDefinition":[{"formKey":"wysiwyg-settings","children":[{"formKey":"wysiwyg-editor","breakpoints":[],"activeFields":[{"name":"content","active":true}]},{"formKey":"wysiwyg-style","breakpoints":[],"activeFields":[{"name":"customStyle","active":true},{"name":"customStyle","active":true},{"name":"customStyle","active":true}]}]}],"form":null},"hideNoData":{"title":"Hide if no data","selectorType":"topLevel","formDefinition":[{"formKey":"tab-hide-data-settings","children":[{"formKey":"tab-hide-data-hide","breakpoints":[],"activeFields":[{"name":"hideEnable","active":true},{"name":"hideData","active":true}]}]}],"form":null},"styles":{"items":[],"formDefinition":[],"selectorType":"topLevel","form":null}}},"model":{"67b320ff-88ba-4f35-ab41-73981a3d4043":{"settings":{"title":"Container - fluid width","width":"fluid","customStyle":[{"customStyle":"[field.165f1de9-336c-42cc-bed2-28ef036ec7e3]"}]},"context-visibility":{"contextVisibility":{"condition":"ALL"}},"styles":{"settings":{"element":"container"},"styles":{"xl":{"background-color":{"value":"[field.fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d]"},"position":{},"top":{},"bottom":{},"left":{},"right":{},"z-index":{},"min-width":{},"max-width":{}}}},"isVariableMode":false,"markup":{"classes":""}},"0782b4fc-ce52-4494-9d39-788ed7e8fa4a":{"settings":{"title":"Container - boxed width","width":"boxed","customStyle":[{"customStyle":"coh-style-position---right"}]},"context-visibility":{"contextVisibility":{"condition":"ALL"}},"styles":{"settings":{"element":"container"},"styles":{"xl":{"display":{},"visibility":{},"overflow":{},"overflow-x":{},"overflow-y":{},"flex-container":{"flex-direction":{},"flex-wrap":{},"justify-content":{},"align-content":{},"align-items":{}},"background-color":{}}}},"isVariableMode":false,"markup":{}},"c6857d35-9264-4a99-b76a-66f4cec46e0d":{"settings":{"title":"WYSIWYG","content":"[field.6b671446-cb09-46cb-b84a-7366da00be36]","customStyle":[{"customStyle":"[field.4c27d36c-a473-47ec-8d43-3b9696d45d74]"},{"customStyle":"[field.e6f07bf5-1bfa-4fef-8baa-62abb3016788]"}]},"context-visibility":{"contextVisibility":{"condition":"ALL"}},"styles":{"settings":{"element":"wysiwyg"}},"hideNoData":{"hideEnable":false}},"59d4f7c1-9bf0-416f-aed2-1c0d3dc53db3":{"settings":{"type":"cohTabContainer","title":"Tab container","responsiveMode":true}},"90394ace-9a36-4aed-a27e-f116e53c5ef1":{"settings":{"type":"cohTabItem","title":"Content","breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"de5a8dd6-fa58-4eb9-89db-26bf1e8fb68a":{"settings":{"type":"cohSection","title":"Text","hideRowHeading":0,"columnCount":"coh-component-field-group-1-col","breakpoints":false,"propertiesMenu":false,"disableScrollbar":true,"disableEllipsisMenu":true,"isOpen":true,"removePadding":0,"breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"6b671446-cb09-46cb-b84a-7366da00be36":{"settings":{"title":"WYSIWYG","type":"cohWysiwyg","schema":{"type":"object"},"machineName":"wysiwyg"},"contextVisibility":{"condition":"ALL"},"model":{"value":{"text":"<p>The European languages are members of the same family. Their separate existence is a myth. For science, music, sport, etc, Europe uses the same vocabulary. The languages only differ in their grammar, their pronunciation and their most common words. Everyone realizes why a new common language would be desirable: one could refuse to pay expensive translators. To achieve this, it would be necessary to have uniform grammar, pronunciation and more common words.<\/p>\n\n<p>If several languages coalesce, the grammar of the resulting language is more simple and regular than that of the individual languages. The new common language will be more simple and regular than the existing European languages. It will be as simple as Occidental; in fact, it will be Occidental. To an English person, it will seem like simplified English, as a skeptical Cambridge friend of mine told me what Occidental is. The European languages are members of the same family. Their separate existence is a myth.<\/p>\n\n<p>For science, music, sport, etc, Europe uses the same vocabulary. The languages only differ in their grammar, their pronunciation and their most common words. Everyone realizes why a new common language would be desirable: one could refuse to pay expensive translators. To achieve this, it would be necessary to have uniform grammar, pronunciation and more common words. If several languages coalesce, the grammar of the resulting language is more simple and regular than that of the individual languages.<\/p>\n","textFormat":"cohesion"}}},"c51b5bde-ce05-455d-8b35-9791c2488cb2":{"settings":{"type":"cohTabItem","title":"Layout and style","breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"27de5221-720a-4382-b68a-fc70fccc01ac":{"settings":{"title":"Background color","type":"cohSection","hideRowHeading":0,"columnCount":"coh-component-field-group-1-col","breakpoints":false,"propertiesMenu":false,"disableScrollbar":true,"disableEllipsisMenu":true,"isOpen":true,"removePadding":0,"breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d":{"settings":{"title":"Color","type":"cohColourPickerOpener","colourPickerOptions":{"flat":true},"schema":{"type":"object"},"availableColors":"$coh-color-light-1,$coh-color-light-2,$coh-color-light-3,$coh-color-white","restrictBy":"colors","machineName":"color"},"contextVisibility":{"condition":"ALL"},"model":{"value":{"name":"White","uid":"white","value":{"hex":"#ffffff","rgba":"rgba(255, 255, 255, 1)"},"wysiwyg":true,"class":".coh-color-white","variable":"$coh-color-white","inuse":true,"link":true}}},"fc0305e5-93bb-4fa4-b49d-11e51928d4a2":{"settings":{"title":"Width","type":"cohSection","hideRowHeading":0,"columnCount":"coh-component-field-group-1-col","breakpoints":false,"propertiesMenu":false,"disableScrollbar":true,"disableEllipsisMenu":true,"isOpen":true,"removePadding":0,"breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"e6f07bf5-1bfa-4fef-8baa-62abb3016788":{"settings":{"title":"Width of text","type":"cohSelect","selectType":"custom","schema":{"type":"string"},"options":[{"label":"Narrow (position right)","value":"coh-style-max-width---narrow"},{"label":"Wide (width of parent)","value":"coh-style-max-width---wide"}],"machineName":"width-of-text"},"contextVisibility":{"condition":"ALL"},"model":{"value":"coh-style-max-width---narrow"}},"ca81f1fd-f274-499b-b14f-b513d9468689":{"settings":{"type":"cohSection","title":"Padding","hideRowHeading":0,"columnCount":"coh-component-field-group-1-col","breakpoints":false,"propertiesMenu":false,"disableScrollbar":true,"disableEllipsisMenu":true,"isOpen":true,"removePadding":0,"breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"165f1de9-336c-42cc-bed2-28ef036ec7e3":{"settings":{"type":"cohSelect","title":"Padding top and bottom of section","selectType":"custom","schema":{"type":"string"},"options":[{"label":"None","value":""},{"label":"Top and bottom","value":"coh-style-padding-top-bottom---large"},{"label":"Top only","value":"coh-style-padding-top---large"},{"label":"Bottom only","value":"coh-style-padding-bottom---large"}],"machineName":"padding-top-and-bottom-of-section"},"contextVisibility":{"condition":"ALL"},"model":{"value":"coh-style-padding-bottom---large"}},"99c8fcb7-faf5-41ca-9307-b15d767ee7c1":{"settings":{"type":"cohSection","title":"Text columns","hideRowHeading":0,"columnCount":"coh-component-field-group-1-col","breakpoints":false,"propertiesMenu":false,"disableScrollbar":true,"disableEllipsisMenu":true,"isOpen":true,"removePadding":0,"breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"4c27d36c-a473-47ec-8d43-3b9696d45d74":{"settings":{"title":"Divide text into columns","type":"cohSelect","selectType":"custom","schema":{"type":"string"},"options":[{"label":"1 Column text (Default)","value":""},{"label":"2 Column text","value":"coh-style-text-columns---two"}],"machineName":"divide-text-into-columns"},"contextVisibility":{"condition":"ALL"},"model":{"value":""}},"2170c5e2-f3e6-4b0f-a378-9629ec9ebfa2":{"settings":{"type":"cohTabItem","title":"Help","breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"cfb80dbb-f6c6-4ae3-8083-5ec5f35da7df":{"settings":{"title":"Help and information","type":"cohSection","hideRowHeading":0,"columnCount":"coh-component-field-group-1-col","breakpoints":false,"propertiesMenu":false,"disableScrollbar":true,"disableEllipsisMenu":true,"isOpen":true,"removePadding":0,"breakpointIcon":""},"contextVisibility":{"condition":"ALL"}},"63e45257-9c1d-45d5-8af1-7b356df559ac":{"settings":{"title":"Help text","type":"cohHelpText","options":{"helpText":"You can divide the text into two columns within the 'Layout and style' tab."}},"contextVisibility":{"condition":"ALL"},"model":{}},"35539d06-7709-4d75-babc-9fc4a4e3657a":{"settings":{"title":"Help text","type":"cohHelpText","options":{"helpText":"You can set the background color of this component within the 'Layout and style' tab. When setting a background color you may also need to apply padding to both the top and the bottom. This can also be set within the 'Layout and style' tab."}},"contextVisibility":{"condition":"ALL"},"model":{}}},"previewModel":{"67b320ff-88ba-4f35-ab41-73981a3d4043":{"settings":{"customStyle":[{"customStyle":""}]},"styles":{"styles":{"xl":{"background-color":{}}}}},"6b671446-cb09-46cb-b84a-7366da00be36":{},"4c27d36c-a473-47ec-8d43-3b9696d45d74":{},"0782b4fc-ce52-4494-9d39-788ed7e8fa4a":{},"27de5221-720a-4382-b68a-fc70fccc01ac":{},"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d":{},"35539d06-7709-4d75-babc-9fc4a4e3657a":{},"63e45257-9c1d-45d5-8af1-7b356df559ac":{},"cfb80dbb-f6c6-4ae3-8083-5ec5f35da7df":{},"fc0305e5-93bb-4fa4-b49d-11e51928d4a2":{},"e6f07bf5-1bfa-4fef-8baa-62abb3016788":{},"c6857d35-9264-4a99-b76a-66f4cec46e0d":{"settings":{"content":{"text":"<p>The European languages are members of the same family. Their separate existence is a myth. For science, music, sport, etc, Europe uses the same vocabulary. The languages only differ in their grammar, their pronunciation and their most common words. Everyone realizes why a new common language would be desirable: one could refuse to pay expensive translators. To achieve this, it would be necessary to have uniform grammar, pronunciation and more common words.<\/p>\n\n<p>If several languages coalesce, the grammar of the resulting language is more simple and regular than that of the individual languages. The new common language will be more simple and regular than the existing European languages. It will be as simple as Occidental; in fact, it will be Occidental. To an English person, it will seem like simplified English, as a skeptical Cambridge friend of mine told me what Occidental is. The European languages are members of the same family. Their separate existence is a myth.<\/p>\n\n<p>For science, music, sport, etc, Europe uses the same vocabulary. The languages only differ in their grammar, their pronunciation and their most common words. Everyone realizes why a new common language would be desirable: one could refuse to pay expensive translators. To achieve this, it would be necessary to have uniform grammar, pronunciation and more common words. If several languages coalesce, the grammar of the resulting language is more simple and regular than that of the individual languages.<\/p>\n","textFormat":"cohesion"},"customStyle":[{"customStyle":""},{"customStyle":""}]}}},"variableFields":{"67b320ff-88ba-4f35-ab41-73981a3d4043":["settings.customStyle.0.customStyle","styles.styles.xl.background-color.value"],"c6857d35-9264-4a99-b76a-66f4cec46e0d":["settings.content","settings.customStyle.0.customStyle","settings.customStyle.1.customStyle"]},"meta":{"fieldHistory":[{"uuid":"6b671446-cb09-46cb-b84a-7366da00be36","type":"form-wysiwyg","machineName":"wysiwyg"},{"uuid":"fdaea1d1-6b7c-4aad-978a-e6981fb5eb7d","type":"form-colorpicker","machineName":"color"},{"uuid":"e6f07bf5-1bfa-4fef-8baa-62abb3016788","type":"form-select","machineName":"width-of-text"},{"uuid":"165f1de9-336c-42cc-bed2-28ef036ec7e3","type":"form-select","machineName":"padding-top-and-bottom-of-section"},{"uuid":"4c27d36c-a473-47ec-8d43-3b9696d45d74","type":"form-select","machineName":"divide-text-into-columns"}]}}
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
