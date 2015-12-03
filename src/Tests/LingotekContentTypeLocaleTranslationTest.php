<?php

namespace Drupal\lingotek\Tests;


use Drupal\node\NodeInterface;

/**
 * Tests translating a node with multiple locales.
 *
 * @group lingotek
 */
class LingotekContentTypeLocaleTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['dblog', 'block', 'node', 'image', 'comment'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    // Add locales.
    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
  }

  /**
   * Tests that a node type can be translated.
   */
  public function testContentTypeTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/config/regional/config-translation');
    $this->drupalGet('/admin/config/regional/config-translation/node_type');
    $this->clickLink(t('Translate'));

    $this->clickLink(t('Upload'));
    $this->assertText(t('Article uploaded successfully'));

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual(3, count($data));
    $this->assertTrue(array_key_exists('name', $data));
    // Cannot use isset, the key exists but we are not providing values, so NULL.
    $this->assertTrue(array_key_exists('description', $data));
    $this->assertTrue(array_key_exists('help', $data));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->clickLink(t('Check upload status'));
    $this->assertText(t('Article status checked successfully'));

    $this->clickLink(t('Request translation'));
    $this->assertText(t('Translation to es_AR requested successfully'));
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.added_target_locale'));

    $this->clickLink(t('Check Download'));
    $this->assertText(t('Translation to es_AR status checked successfully'));

    $this->clickLink('Download');
    $this->assertText(t('Translation to es_AR downloaded successfully'));

    // Check that the edit link is there.
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath. '/admin/structure/types/manage/article/translate/es-ar/edit');
  }

}
